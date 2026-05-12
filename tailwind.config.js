const fs = require('fs');
const path = require('path');

// Load safelist from file (workaround for Windows Tailwind binary not parsing "/" opacity classes in .twig)
const safelistFile = path.resolve(__dirname, 'tailwind.safelist.txt');
const safelist = fs.existsSync(safelistFile)
  ? fs.readFileSync(safelistFile, 'utf-8').split('\n').map(s => s.trim()).filter(Boolean)
  : [];

/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  safelist: safelist,
  theme: {
    extend: {
      colors: {
        formation: {
          950: '#051f3d',
          900: '#022E69',
          800: '#0a2854',
          700: '#0d3d6f',
          600: '#145EB7',
          500: '#1e7dd9',
          400: '#3FA9F5',
          300: '#74c4f8',
          200: '#a8dcfb',
          100: '#d0edfd',
          50: '#e8f6fe',
        }
      }
    },
  },
  plugins: [],
}
