# Hướng dẫn AI phát triển tính năng

Tài liệu này giúp AI và người phát triển mở rộng Equipment Manager theo đúng kiến trúc hiện tại: giao diện dễ dùng, hiệu năng tốt, có thể kế thừa và bảo trì, đồng thời không làm thay đổi ngoài ý muốn các chức năng khác.

## 1. Nguyên tắc bắt buộc

1. **Khảo sát trước khi sửa:** tìm trang, component, service, endpoint, model và test đang sở hữu hành vi cần thay đổi. Đọc pattern gần nhất rồi làm theo; không suy đoán dựa trên tên file.
2. **Giữ phạm vi nhỏ:** chỉ thay đổi những phần cần thiết cho yêu cầu. Không “tiện tay” refactor, đổi API, cách phân quyền, response, validation hay giao diện của tính năng khác.
3. **Tái sử dụng có chọn lọc:** ưu tiên component, helper, service và quy ước sẵn có. Chỉ tạo abstraction dùng chung khi có ít nhất hai nơi dùng thực tế và abstraction làm luồng rõ hơn; tránh framework riêng hoặc component tổng quát khó hiểu.
4. **Không làm mất dữ liệu:** không xóa/ghi đè dữ liệu theo mặc định. Import, bulk update/delete và migration cần quy tắc rõ ràng, kiểm tra phạm vi tổ chức và phản hồi kết quả.
5. **Tối ưu có số liệu hoặc nguyên nhân cụ thể:** không thêm cache, request nền, preload hay truy vấn phức tạp chỉ vì “có thể nhanh hơn”. Mọi cache phải có key đúng phạm vi và invalidation đúng lúc.
6. **Báo lỗi trung thực:** không nuốt exception, trả kết quả thành công giả, hoặc đổi lỗi thành mảng rỗng không phân biệt với dữ liệu rỗng hợp lệ.
7. **Hoàn tất mới bàn giao:** chạy kiểm tra tương ứng, đọc diff, nêu rõ phần đã làm, phần chưa kiểm chứng và bất kỳ điều kiện triển khai nào.

## 2. Quy trình làm việc đề xuất

### Bước 1: Xác nhận yêu cầu và ranh giới

- Viết lại kết quả người dùng mong đợi bằng hành vi quan sát được.
- Xác định luồng chính, trạng thái rỗng/lỗi/loading, quyền truy cập và dữ liệu liên quan.
- Nếu có nhiều lựa chọn làm thay đổi dữ liệu hoặc trải nghiệm đáng kể, hỏi trước; nếu không, dùng cách ít rủi ro nhất và ghi rõ giả định.

### Bước 2: Tìm đúng điểm mở rộng

- Dùng tìm kiếm code và các trang/component hiện hành để lần theo luồng từ UI đến API và DB.
- Kiểm tra `git status` trước khi sửa. Không ghi đè hay xóa thay đổi có sẵn của người dùng.
- Đọc test, route middleware, enum, form request, cache và realtime liên quan trước khi thay đổi hành vi.
- Kiểm tra cùng một khái niệm có nhiều bề mặt: danh sách/thẻ/bảng/mobile, tạo/sửa/xem, API dùng chung hoặc event realtime.

### Bước 3: Triển khai theo chiều dọc

Thay đổi trọn luồng cần thiết: giao diện → service/API → validation/authorization → business logic → dữ liệu/cache/realtime → test/tài liệu. Không để lại nút UI giả, endpoint chưa nối hoặc backend không được sử dụng.

### Bước 4: Xác minh và bàn giao

- Chạy test nhỏ nhất phù hợp, sau đó build/typecheck/lint liên quan nếu có.
- Kiểm tra diff để bảo đảm không có file sinh tự động, cấu hình cục bộ, `.env`, token hoặc thay đổi ngoài yêu cầu.
- Báo cáo hành vi mới, kiểm tra đã chạy, giới hạn chưa kiểm tra và migration/deploy cần thiết.

## 3. Kiến trúc và nơi đặt code

### Frontend

Frontend là Vue SPA trong `Frontend/src`:

- `modules/<domain>/views/`: trang theo route và điều phối trạng thái trang.
- `modules/<domain>/components/`: component thuộc riêng domain.
- `modules/<domain>/services/`: gọi API qua `axiosClient`, dùng endpoint tập trung.
- `modules/<domain>/stores/`: trạng thái dùng chung của domain nếu nhiều component cần.
- `core/components/`: component dùng chung thực sự giữa các domain.
- `core/api/endpoints.js`: khai báo endpoint API.
- `core/services/requestCache.js`: cache/deduplicate request trong phiên.
- `core/casl/`: ánh xạ permission backend sang CASL action/subject.
- `core/helpers/`: helper dùng chung, không đặt business logic domain tại đây.

**Quy tắc:** view điều phối; component hiển thị và phát event; service chịu trách nhiệm HTTP/cache invalidation; store chịu trách nhiệm trạng thái dùng chung và cập nhật tại chỗ. Không gọi `axios` trực tiếp trong component nếu có thể đi qua service hiện tại.

### Backend

Backend là Laravel trong `Backend/app/Modules/<Module>`:

- `Routes/`: route và middleware/permission.
- `Controllers/`: HTTP boundary, nhận request và định dạng response.
- `Requests/`: validation/authentication của input khi phù hợp.
- `Services/`: business logic, transaction, cache invalidation và event.
- `Models/`: quan hệ, cast và scope của dữ liệu.
- `Resources/`: định dạng API response.
- `Enums/`: tập giá trị chuẩn hóa.
- `Imports/`, `Exports/`: xử lý Excel theo pattern hiện có.

Controller nên mỏng; đưa nghiệp vụ dùng lại được vào service. Không bỏ middleware hoặc authorization để “sửa nhanh” lỗi quyền. Kiểm tra route hiện tại để biết API có nằm trong nhóm `auth:sanctum`, `set.permissions.team`, `log.activity` hay middleware permission riêng.

## 4. Bảo toàn hành vi và an toàn dữ liệu

- Giữ nguyên tên/kiểu field, status code, envelope response và semantics hiện có, trừ khi yêu cầu bắt buộc đổi.
- Với multi-tenancy, mọi truy vấn đọc/ghi phải đúng organization hiện hành. Lấy organization từ ngữ cảnh/header đã được xác thực, không tin `organization_id` tùy ý trong body.
- Dùng transaction khi một thao tác cập nhật nhiều bảng mà cần tính nguyên tử.
- Dữ liệu trùng trong import phải có chính sách tường minh; mặc định an toàn là bỏ qua và báo dòng lỗi, không `updateOrCreate` nếu người dùng chưa yêu cầu ghi đè.
- Migration phải có `down()` hợp lý, xử lý trạng thái dữ liệu hiện có và không xóa cột/bảng không thuộc phạm vi.
- Khi xóa phòng/thiết bị, xem xét quan hệ và quy tắc thu hồi/bảo toàn dữ liệu hiện hành.
- Không thay đổi route hoặc permission để làm nút hiện ra; UI ẩn/hiện theo CASL nhưng backend vẫn phải bảo vệ endpoint.

## 5. Hiệu năng và dữ liệu

- Tránh N+1: dùng eager loading, `withCount`, aggregate query hoặc batch lookup khi phù hợp.
- Không tải toàn bộ dataset vào SPA nếu API đã có phân trang/lọc; giữ server-side pagination cho danh sách lớn.
- Chỉ chọn field cần thiết cho dropdown/thẻ và chỉ gọi API khi cần. Deduplicate request bằng request cache hiện có nếu cùng key và cùng ngữ cảnh.
- Cache key phải bao gồm tổ chức và tham số ảnh hưởng kết quả. Sau mutation, invalidation phải bao phủ đúng namespace/key liên quan; không xóa cache toàn cục nếu chỉ đổi một tổ chức.
- Không dùng cache như thay thế cho authorization hoặc dữ liệu có thể thay đổi mà chưa có quy tắc invalidation.
- Với thống kê danh sách, ưu tiên một truy vấn group/aggregate theo tập ID trang hiện tại hơn truy vấn lặp lại cho từng hàng.
- Realtime cần cập nhật UI/cache đúng domain và event naming hiện tại; không tải lại toàn trang hoặc gọi lại mọi API sau một mutation nhỏ nếu có thể cập nhật state an toàn.
- Không ghi payload nhạy cảm vào log hoặc broadcast; event chỉ gửi field cần thiết cho người nhận.

## 6. UI/UX: tự nhiên, rõ ràng, responsive

- Theo phong cách đang dùng: Vue 3, Vuetify, Tailwind, spacing, màu, icon và component có sẵn. Đừng đưa thêm thư viện nếu không thật sự cần.
- Dùng nhãn tiếng Việt ngắn, tự nhiên, nhất quán với thuật ngữ trong ứng dụng. Tránh thông báo kỹ thuật cho người dùng; lỗi chi tiết có thể ghi log an toàn và hiển thị hướng xử lý.
- Ưu tiên thứ bậc thị giác rõ: tiêu đề/mô tả, hành động chính, hành động phụ, nội dung. Không đặt hai hành động chính cạnh tranh nhau.
- Giữ vị trí và hành vi quen thuộc của các thao tác hiện hành; không che hoặc di chuyển điều khiển làm mất khả năng phát hiện.
- Thiết kế mobile trước khi hoàn tất: nút có thể xuống dòng/chiếm chiều rộng phù hợp, form một cột, modal cuộn được, bảng có cách xem phù hợp trên màn hình nhỏ, không tràn ngang ngoài chủ ý.
- Có trạng thái loading, empty, error, success và disabled phù hợp; không để màn hình trống khiến người dùng không biết hệ thống đang làm gì.
- Form phải có label, ví dụ đầu vào hữu ích, validation cạnh field và giữ dữ liệu khi lỗi. Thao tác phá hủy cần xác nhận theo pattern sẵn có.
- Hỗ trợ bàn phím, focus, target chạm đủ lớn, tương phản màu và text/aria cho điều khiển không có nhãn.
- Không dùng animation hoặc toast cho mọi thay đổi; phản hồi vừa đủ, không làm gián đoạn luồng công việc.

## 7. Tạo component dùng lại

Tạo component dùng chung khi nhiều màn hình thực sự chia sẻ cùng hành vi/giao diện (ví dụ hộp thoại import/export Excel). Component nên:

- Có props rõ và có kiểu/ràng buộc phù hợp; không tự truy cập store/API domain nếu caller có thể truyền dependency.
- Phát event có tên hành động rõ; không sửa object prop trực tiếp.
- Không chứa logic dành riêng cho một domain nếu không được cấu hình minh bạch.
- Có trạng thái loading/lỗi/empty riêng; lỗi không bị component cha bỏ qua.
- Có test cho hành vi dùng chung và giữ API component đủ nhỏ.

Không tạo component chỉ để bọc một dòng markup nếu điều đó làm luồng khó theo dõi hơn.

## 8. Kiểm thử

Frontend có lệnh tại `Frontend/package.json`:

```powershell
npm --prefix Frontend test
npm --prefix Frontend run build
```

Backend:

```powershell
php artisan test
php artisan route:list
```

Chạy từ thư mục `Backend` đối với lệnh Artisan; cú pháp file PHP có thể xác minh bằng `php -l <file>`. Dùng đúng interpreter/runtime của môi trường dự án. Nếu test không chạy vì thiếu driver/dịch vụ ngoài, ghi chính xác giới hạn thay vì tuyên bố đã xác minh.

Tối thiểu nên có test cho:

- Hành vi cốt lõi và biên đầu vào hợp lệ/không hợp lệ.
- Quyền truy cập và cách ly tổ chức nếu endpoint/domain có multi-tenancy.
- Trạng thái empty/error và cập nhật cache/state khi mutation nếu liên quan.
- Component dùng chung ở viewport nhỏ hoặc hành vi responsive có thể kiểm chứng.

## 9. Checklist trước khi hoàn tất

- [ ] Tôi đã đọc pattern hiện có và xác định các bề mặt cần cập nhật.
- [ ] Thay đổi chỉ nằm trong phạm vi yêu cầu; không làm mất thay đổi người dùng.
- [ ] API, permission, organization scope, validation và response vẫn nhất quán.
- [ ] Không có N+1, tải dư dữ liệu, cache key sai tổ chức hoặc thiếu invalidation.
- [ ] Giao diện có loading/empty/error/success, dễ hiểu và dùng được trên điện thoại.
- [ ] Component/helper mới có tái sử dụng thực chất và không tạo abstraction dư thừa.
- [ ] Test/build/lint phù hợp đã chạy; giới hạn được nêu rõ.
- [ ] Diff không chứa secret, file runtime, task tạm hoặc output build không được yêu cầu.
- [ ] Tài liệu liên quan được cập nhật.

## 10. Tài liệu liên quan

- [README dự án](../README.md): mục tiêu, module và cách khởi chạy.
- [Hướng dẫn Docker](./DOCKER_GUIDE.md): chạy dịch vụ và xem log.
- [AGENTS.md](../AGENTS.md) và [GEMINI.md](../GEMINI.md): quy tắc làm việc của coding agent.
