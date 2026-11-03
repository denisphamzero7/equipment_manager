import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'

export const vuetify = createVuetify({
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#2563eb', // Blue-600
          'primary-darken-1': '#1d4ed8',
          secondary: '#475569', // Slate-600
          accent: '#8b5cf6', // Violet-500
          success: '#10b981', // Emerald-500
          warning: '#f59e0b', // Amber-500
          error: '#ef4444', // Red-500
          info: '#0ea5e9', // Sky-500
          background: '#f8fafc',
          surface: '#ffffff',
          'surface-variant': '#f1f5f9',
          'on-surface-variant': '#64748b',
        },
      },
      dark: {
        dark: true,
        colors: {
          primary: '#3b82f6', // Blue-500 cho độ tương phản tốt trên nền tối
          'primary-darken-1': '#2563eb',
          secondary: '#94a3b8', // Slate-400
          accent: '#a78bfa', // Violet-400
          success: '#10b981', // Emerald-500
          warning: '#f59e0b', // Amber-500
          error: '#ef4444', // Red-500
          info: '#38bdf8', // Sky-400
          background: '#0b1120', // Slate-950 / Dark Navy sâu
          surface: '#1e293b', // Slate-800
          'surface-variant': '#334155', // Slate-700
          'on-surface-variant': '#94a3b8',
        },
      },
    },
  },
  defaults: {
    VBtn: {
      rounded: 'lg',
      elevation: 0,
      height: 40,
      style: [{ textTransform: 'none', fontWeight: 600, letterSpacing: 'normal' }],
    },
    VCard: {
      rounded: 'xl',
      elevation: 0,
      border: 'thin',
    },
    VTextField: {
      variant: 'outlined',
      density: 'comfortable',
      rounded: 'lg',
      color: 'primary',
    },
    VSelect: {
      variant: 'outlined',
      density: 'comfortable',
      rounded: 'lg',
      color: 'primary',
    },
    VTextarea: {
      variant: 'outlined',
      density: 'comfortable',
      rounded: 'lg',
      color: 'primary',
    },
    VChip: {
      rounded: 'lg',
      elevation: 0,
    },
    VDialog: {
      scrim: 'rgba(15, 23, 42, 0.45)',
    },
  },
})

export default vuetify
