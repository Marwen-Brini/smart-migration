/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        'safe': '#10b981',
        'warning': '#f59e0b',
        'danger': '#ef4444',
      },
    },
  },
  plugins: [],
}
