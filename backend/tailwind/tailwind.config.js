/* Budowa CSS Tailwinda na serwerze zamiast skryptu Play CDN w przeglądarce
   (assets/js/tailwind.js, 400 KB blokujących wyświetlanie na telefonie).
   Bez własnego theme - tak samo jak dotychczasowy skrypt, który działał na
   domyślnej konfiguracji 3.4.17. Zbudowanie: backend/tailwind/build.sh */
module.exports = {
  content: [
    '../../*.php',
    '../../{api,auth,assets,components,poradniki,backend/mail}/**/*.{php,js,html}',
    '!../../assets/js/tailwind.js',
    '!../../**/vendor/**',
  ],
};
