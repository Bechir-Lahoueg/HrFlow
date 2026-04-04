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
    extend: {},
  },
  plugins: [],
}
