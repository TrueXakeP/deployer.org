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
  if (!terminalNode) {
    return;
  }
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
    '<img src="https://deployer.org/assets/tit.gif" alt="tit">\n',
    ''
  ];
}

function shuffle(a) {
  let j, x, i;
  for (i = a.length; i; i--) {
    j = Math.floor(Math.random() * i);
    x = a[i - 1];
    a[i - 1] = a[j];
    a[j] = x;
  }
}

const girls = [
  'girl_1.jpg', 'girl_32.gif', 'girl_55.jpg', 'girl_78.jpg', 'girl_10.gif', 'girl_33.jpg', 'girl_56.jpg', 'girl_79.jpg', 'girl_11.jpeg', 'girl_34.jpg', 'girl_57.jpg', 'girl_8.jpg', 'girl_12.jpg', 'girl_35.jpg', 'girl_58.jpg', 'girl_80.jpg', 'girl_13.jpg', 'girl_36.jpg', 'girl_59.jpg', 'girl_81.jpg', 'girl_14.jpeg', 'girl_37.jpg', 'girl_6.gif', 'girl_82.jpg', 'girl_15.jpg', 'girl_38.jpg', 'girl_60.gif', 'girl_83.jpg', 'girl_16.gif', 'girl_39.jpg', 'girl_61.jpg', 'girl_84.jpg', 'girl_17.jpg', 'girl_4.gif', 'girl_62.jpg', 'girl_85.jpg', 'girl_18.jpg', 'girl_40.jpg', 'girl_63.jpg', 'girl_86.jpg', 'girl_19.jpg', 'girl_41.jpg', 'girl_64.jpg', 'girl_87.png', 'girl_2.gif', 'girl_42.jpeg', 'girl_65.jpg', 'girl_88.jpg', 'girl_20.jpg', 'girl_43.jpg', 'girl_66.jpg', 'girl_89.jpg', 'girl_21.jpg', 'girl_44.png', 'girl_67.jpg', 'girl_9.jpg', 'girl_22.jpg', 'girl_45.jpg', 'girl_68.jpg', 'girl_90.jpg', 'girl_23.gif', 'girl_46.gif', 'girl_69.jpg', 'girl_91.png', 'girl_24.jpg', 'girl_47.jpg', 'girl_7.jpg', 'girl_92.jpg', 'girl_25.gif', 'girl_48.jpg', 'girl_70.jpg', 'girl_93.jpg', 'girl_26.png', 'girl_49.jpg', 'girl_71.jpg', 'girl_94.jpg', 'girl_27.jpg', 'girl_5.jpg', 'girl_72.jpg', 'girl_95.jpg', 'girl_28.jpg', 'girl_50.gif', 'girl_73.jpg', 'girl_96.jpg', 'girl_29.jpg', 'girl_51.jpg', 'girl_74.jpg', 'girl_97.jpg', 'girl_3.gif', 'girl_52.jpg', 'girl_75.gif', 'girl_98.jpg', 'girl_30.jpg', 'girl_53.jpg', 'girl_76.gif', 'girl_31.jpg', 'girl_54.jpg', 'girl_77.jpg'
];
shuffle(girls);

function girl() {
  return [
    `<img src="https://deployer.org/assets/girls/${girls.pop()}" alt="girl">`,
    ``
  ];
}
