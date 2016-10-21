const commandList = {
  dep,
  help,
  ls,
  php,
  girl,
  tit
};

const ok = `<svg width="14" height="14" viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M116.158,29.336l-4.975-4.975c-3.469-3.469-9.088-3.478-12.549-0.019L48.103,74.875L29.364,56.136  c-3.459-3.46-9.078-3.45-12.549,0.021l-4.974,4.974c-3.47,3.47-3.48,9.089-0.02,12.549L41.8,103.657  c1.741,1.741,4.026,2.602,6.31,2.588c2.279,0.011,4.559-0.852,6.297-2.59l61.771-61.771  C119.637,38.424,119.631,32.807,116.158,29.336z" fill="#2FDB2F"/></svg>`;

export function init() {
  const terminalNode = document.querySelector('.terminal');
  const scrollNode = terminalNode.querySelector('.scroll');
  const logNode = terminalNode.querySelector('.log');
  const formNode = terminalNode.querySelector('form');
  const inputNode = terminalNode.querySelector('#input');

  function append(str) {
    const text = document.createElement('div');
    text.innerHTML = str;
    logNode.appendChild(text);
    scrollNode.scrollTop = scrollNode.scrollHeight;
  }

  formNode.addEventListener('submit', function (event) {
    event.preventDefault();

    const command = inputNode.value;
    const [name, ...args] = command.split(' ');
    inputNode.value = '';

    if (ga) ga('send', 'event', 'console', 'submit', command);

    append('&gt; ' + command);

    if (commandList[name]) {
      const output = commandList[name](...args);

      const delay = () => setTimeout(() => {
        if (output.length > 0) {
          append(output.shift());
          delay();
        }
      }, 1000);

      append(output.shift());
      delay();
    } else {
      append('Sorry but this command can not be run in the emulator.');
    }
  });

  inputNode.addEventListener('keydown', function (event) {
    if (event.keyCode === 9) { // Tab key
      event.preventDefault();
      const prefix = inputNode.value;
      let s = Object.keys(commandList);

      if (prefix)
        s = s.filter(name => name.indexOf(prefix) === 0);

      if (s.length === 1 && s[0] === prefix)
        return;

      if (s.length > 0) {
        append('&gt; ' + prefix);
        append(s.join('  '));
      }
    }
  });

  document.addEventListener('keydown', function (event) {
    if (!event.ctrlKey && !event.altKey && !event.metaKey) {
      inputNode.focus();
    }
  });

  terminalNode.addEventListener('click', () => inputNode.focus());

  let prefill = 'dep deploy'.split('');
  const fill = () => setTimeout(() => {
    if (prefill.length > 0) {
      inputNode.value += prefill.shift();
      fill();
    }
  }, 200);

  setTimeout(() => fill(), 3000);
}

function dep(arg = null) {
  if (arg === `deploy`) {
    return [
      `${ok} Executing task <b>deploy:prepare</b>`,
      `${ok} Executing task <b>deploy:release</b>`,
      `${ok} Executing task <b>deploy:update_code</b>`,
      `${ok} Executing task <b>deploy:shared</b>`,
      `${ok} Executing task <b>deploy:vendors</b>`,
      `${ok} Executing task <b>deploy:migrate</b>`,
      `${ok} Executing task <b>deploy:warmup</b>`,
      `${ok} Executing task <b>deploy:symlink</b>`,
      `${ok} Executing task <b>cleanup</b>`,
      `<b>Successfully deployed!</b>`
    ];
  } else if (arg === `rollback`) {
    return [
      `${ok} Restoring previous releases`,
      `<b>Successfully restored!</b>`
    ];
  } else if (arg === `migrate`) {
    return [
      `${ok} Migrating database`,
      `<b>Successfully migrated!</b>`
    ];
  } else {
    return [
      `Deployer\n` +
      `\n` +
      `Usage:\n` +
      `  dep [command] [options]\n` +
      `\n` +
      `Available commands:\n` +
      `  help          Displays help for a command\n` +
      `  list          Lists commands\n` +
      `  deploy        Deploy project\n` +
      `  rollback      Rollback to previous release\n` +
      `  migrate       Migrate database\n`
    ];
  }
}

function help() {
  return [
    'This is an example of the console to try Deployer in your browser.\n' +
  'Try type the following commands and press enter:\n' +
  'dep\n' +
  'dep deploy\n' +
  'dep rollback\n' +
  'ls\n'
  ];
}

function ls() {
  return [
    'bin\n' +
    'src\n' +
    'vendor\n' +
    '.gitignore\n' +
    'deploy.php\n'
  ];
}

function php() {
  return [
    'PHP 600 (cli) (built: Feb 30 2017 10:96:69)'
  ];
}

function tit() {
  return [
    '<img src="https://deployer.org/tit.gif" alt="">\n',
    ''
  ];
}

function girl() {
  return [
    '<img src="https://66.media.tumblr.com/89e0d31f04445b582367dc55fb156a67/tumblr_of2t1mgstD1t0j8ebo1_1280.jpg" alt="">',
    ''
  ];
}
