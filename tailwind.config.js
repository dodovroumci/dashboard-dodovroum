/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{js,ts,vue}',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#ff6b35',
          dark: '#d94e1f',
          light: '#ff9e73',
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}

