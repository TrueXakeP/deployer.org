import hljs from './highlight.js/index';

document.addEventListener('DOMContentLoaded', () => {
  highlightCodeBlocks();
  setTimeout(showAds, 3000);
});

function highlightCodeBlocks() {
  const blocks = document.querySelectorAll('pre code');
  for (let i = 0; i < blocks.length; i++) {
    hljs.highlightBlock(blocks[i]);
  }
}

function showAds() {
  const ads = document.querySelector('#carbonads');
  if (ads) {
    ads.classList.add('-visible');
  }
}
