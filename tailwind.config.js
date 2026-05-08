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
        primary: '#1b587c',
        secondary: '#b35e06',
        dark: '#495057',
        link: '#4351a0',
      },
      fontFamily: {
        heading: ['"Josefin Sans"', '"Impact"', '"Arial"', 'sans-serif'],
        body: ['"Montserrat"', '"Arial"', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
