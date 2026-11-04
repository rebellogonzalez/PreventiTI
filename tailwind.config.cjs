module.exports = {
  content: ['./resources/views/**/*.blade.php', './public/js/**/*.js', '!./public/js/vendor/**'],
  theme: {
    extend: {
      colors: { brand: { navy: '#0F2942', blue: '#1E3A8A', cyan: '#0284C7', dark: '#0F172A' } },
    },
  },
  plugins: [],
};
