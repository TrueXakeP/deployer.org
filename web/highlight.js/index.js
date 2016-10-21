const hljs = require('./highlight');
hljs.registerLanguage('js', require('./languages/javascript'));
hljs.registerLanguage('xml', require('./languages/xml'));
hljs.registerLanguage('php', require('./languages/php'));

hljs.configure({
  languages: [
    'js',
    'xml',
    'php'
  ]
});

export default hljs;
