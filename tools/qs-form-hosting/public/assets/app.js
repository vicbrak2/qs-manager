const frame = document.querySelector('[data-app-frame]');
const loading = document.querySelector('[data-loading]');
const errorPanel = document.querySelector('[data-load-error]');
const retry = document.querySelector('[data-retry]');
const sourceUrl = frame.src;

let loadTimer = window.setTimeout(showError, 20000);

frame.addEventListener('load', () => {
  window.clearTimeout(loadTimer);
  loading.hidden = true;
  errorPanel.hidden = true;
});

retry.addEventListener('click', () => {
  errorPanel.hidden = true;
  loading.hidden = false;
  frame.src = `${sourceUrl}${sourceUrl.includes('?') ? '&' : '?'}reload=${Date.now()}`;
  loadTimer = window.setTimeout(showError, 20000);
});

function showError() {
  loading.hidden = true;
  errorPanel.hidden = false;
}
