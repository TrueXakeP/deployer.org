import hljs from './highlight.js/index';
import {init as initTerminal} from './terminal';

document.addEventListener('DOMContentLoaded', () => {
  highlightCodeBlocks();
  initTerminal();
});

function highlightCodeBlocks() {
  const blocks = document.querySelectorAll('pre code');
  for (let i = 0; i < blocks.length; i++) {
    hljs.highlightBlock(blocks[i]);
  }
}
