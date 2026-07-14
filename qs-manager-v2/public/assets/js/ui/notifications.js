
import { escapeHtml } from './formatting.js';
import { $ as dom$ } from '../dom.js';

export function notify(message, type = 'success', duration = 5000) {
  if (typeof type === 'boolean') {
    type = type ? 'error' : 'success';
  }

  const box = dom$('#message');
  if (box) {
    box.textContent = message;
    if (type === 'error' || type === true) {
      box.className = 'message error show';
    } else if (type === 'success') {
      box.className = 'message success show';
    } else {
      box.className = 'message show';
    }
    window.clearTimeout(notify.timer);
    if (duration > 0 && type !== 'loading') {
      notify.timer = window.setTimeout(() => box.classList.remove('show'), duration);
    }
  }

  let container = dom$('#toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  
  const renderContent = (msg, t) => {
    let content = '';
    if (t === 'loading') {
      content += '<span class="spinner"></span>';
    }
    content += `<span>${escapeHtml(msg)}</span>`;
    return content;
  };

  toast.innerHTML = renderContent(message, type);
  container.appendChild(toast);

  window.setTimeout(() => toast.classList.add('show'), 10);

  let dismissTimer;
  const dismiss = () => {
    toast.classList.remove('show');
    window.setTimeout(() => toast.remove(), 300);
  };

  const startTimer = (d) => {
    if (type !== 'loading' && d > 0) {
      dismissTimer = window.setTimeout(dismiss, d);
    }
  };

  startTimer(duration);

  return {
    dismiss,
    update: (newMsg, newType = 'success', newDuration = 5000) => {
      if (dismissTimer) clearTimeout(dismissTimer);
      if (typeof newType === 'boolean') {
        newType = newType ? 'error' : 'success';
      }
      toast.className = `toast ${newType}`;
      toast.innerHTML = renderContent(newMsg, newType);
      
      if (newType !== 'loading' && newDuration > 0) {
        dismissTimer = window.setTimeout(dismiss, newDuration);
      }

      if (box) {
        box.textContent = newMsg;
        if (newType === 'error' || newType === true) {
          box.className = 'message error show';
        } else if (newType === 'success') {
          box.className = 'message success show';
        } else {
          box.className = 'message show';
        }
        window.clearTimeout(notify.timer);
        if (newDuration > 0 && newType !== 'loading') {
          notify.timer = window.setTimeout(() => box.classList.remove('show'), newDuration);
        }
      }
    }
  };
}
