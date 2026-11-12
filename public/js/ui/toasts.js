import { escapeHTML } from '../lib/html.js';

export function initializeToasts() {
  window.showToast = function (title, message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-item border-l-4 ${
      type === 'success' ? 'border-emerald-500' :
      type === 'warning' ? 'border-amber-500' :
      type === 'danger' ? 'border-rose-500' : 'border-blue-500'
    }`;

    const iconColor = type === 'success' ? 'text-emerald-500' :
                      type === 'warning' ? 'text-amber-500' :
                      type === 'danger' ? 'text-rose-500' : 'text-blue-500';

    const iconSvg = type === 'success' 
      ? `<svg class="w-5 h-5 ${iconColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`
      : type === 'warning'
      ? `<svg class="w-5 h-5 ${iconColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`
      : `<svg class="w-5 h-5 ${iconColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;

    toast.innerHTML = `
      <div class="flex-shrink-0">${iconSvg}</div>
      <div class="min-w-0 flex-1 text-sm">
        <h4 class="font-semibold text-slate-800 leading-tight">${escapeHTML(title)}</h4>
        <p class="text-slate-600 text-xs mt-0.5 leading-normal">${escapeHTML(message)}</p>
      </div>
      <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    `;

    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 4500);
  };
}
