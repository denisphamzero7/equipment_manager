<?php

namespace App\Modules\Core\Imports\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Imports\HeadingRowExtractor;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait ValidatesSpreadsheetImport
{
    protected int $successCount = 0;

    protected int $failedCount = 0;

    protected array $errors = [];

    protected bool $headingsValidated = false;

    protected int $totalDataRows = 0;

    protected int $maxErrorDetails = 100;

    abstract protected function validateHeadings(array $headings): void;

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event): void {
                $worksheet = $event->sheet->getDelegate();
                $sheetIndex = $worksheet->getParent()
                    ? $worksheet->getParent()->getIndex($worksheet)
                    : 0;

                $headings = $this->extractNormalizedHeadings($worksheet);
                $hasHeaderText = ! empty($headings);

                if (! $hasHeaderText && $worksheet->getHighestDataRow() <= 1) {
                    if ($sheetIndex === 0) {
                        throw ValidationException::withMessages([
                            'file' => ['Tệp Excel trống, không có dữ liệu để nhập.'],
                        ]);
                    }

                    return;
                }

                $this->validateHeadings($headings);
                $this->headingsValidated = true;

                if ($sheetIndex === 0 && $worksheet->getHighestDataRow() < 2) {
                    throw ValidationException::withMessages([
                        'file' => ['Tệp Excel chỉ có dòng tiêu đề, chưa có dữ liệu để nhập. Vui lòng điền ít nhất một dòng dữ liệu.'],
                    ]);
                }
            },
            AfterImport::class => function (): void {
                if ($this->totalDataRows === 0) {
                    throw ValidationException::withMessages([
                        'file' => ['Tệp Excel không có dữ liệu để nhập. Vui lòng điền ít nhất một dòng dữ liệu hợp lệ.'],
                    ]);
                }
            },
        ];
    }

    public function getResult(): array
    {
        $errors = $this->errors;
        if ($this->failedCount > count($errors)) {
            $remaining = $this->failedCount - count($errors);
            $errors[] = "... và {$remaining} dòng lỗi khác (chỉ hiển thị {$this->maxErrorDetails} lỗi đầu tiên).";
        }

        return [
            'success_count' => $this->successCount,
            'failed_count' => $this->failedCount,
            'errors' => $errors,
        ];
    }

    protected function prepareNonEmptyRows(Collection $rows): Collection
    {
        $calledDirectly = ! $this->headingsValidated;

        if ($calledDirectly) {
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'file' => ['Tệp Excel không có dữ liệu để nhập. Vui lòng điền ít nhất một dòng dữ liệu.'],
                ]);
            }

            $firstRow = $rows->first();
            $rawKeys = $firstRow instanceof Collection
                ? $firstRow->keys()->all()
                : array_keys((array) $firstRow);

            $headings = array_values(array_filter(
                array_map(fn ($key) => is_string($key) ? trim($key) : '', $rawKeys),
                fn (string $key) => $key !== ''
            ));

            $this->validateHeadings($headings);
            $this->headingsValidated = true;
        }

        $nonEmptyRows = $rows->filter(fn ($row) => $this->hasAnyValue($row));
        $this->totalDataRows += $nonEmptyRows->count();

        if ($calledDirectly && $nonEmptyRows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => ['Tệp Excel không có dữ liệu để nhập. Vui lòng điền ít nhất một dòng dữ liệu hợp lệ.'],
            ]);
        }

        return $nonEmptyRows;
    }

    protected function extractNormalizedHeadings(Worksheet $worksheet): array
    {
        $extracted = HeadingRowExtractor::extract($worksheet, $this);

        return array_values(array_filter(
            array_map(fn ($heading) => is_string($heading) ? trim($heading) : '', $extracted),
            fn (string $heading) => $heading !== ''
        ));
    }

    protected function hasAnyValue(mixed $row): bool
    {
        $values = $row instanceof Collection ? $row->all() : (array) $row;

        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function hasAnyHeading(array $headings, array $candidates): bool
    {
        return count(array_intersect($headings, $candidates)) > 0;
    }

    protected function nullableValue(mixed $value): mixed
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return is_string($value) ? trim($value) : $value;
    }

    protected function fail(int $rowNumber, string $message): void
    {
        $this->failedCount++;
        if (count($this->errors) < $this->maxErrorDetails) {
            $this->errors[] = "Dòng {$rowNumber}: {$message}";
        }
    }
}
