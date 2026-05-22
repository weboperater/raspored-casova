(function () {
  function hexToRgb(hex) {
    const value = String(hex || '').replace('#', '').trim();
    if (!/^[0-9a-f]{6}$/i.test(value)) return null;
    return [
      parseInt(value.slice(0, 2), 16),
      parseInt(value.slice(2, 4), 16),
      parseInt(value.slice(4, 6), 16),
    ];
  }

  function applyDynamicStyles(root) {
    const scope = root || document;
    scope.querySelectorAll('[data-color]').forEach((el) => {
      const rgb = hexToRgb(el.dataset.color);
      if (!rgb) return;
      el.style.setProperty('--item-color', `#${el.dataset.color.replace('#', '')}`);
      el.style.setProperty('--item-rgb', rgb.join(', '));
    });
    scope.querySelectorAll('[data-progress]').forEach((el) => {
      const value = Number(el.dataset.progress);
      if (!Number.isFinite(value)) return;
      el.style.width = `${Math.min(100, Math.max(0, value))}%`;
    });
  }

  window.applyDynamicStyles = applyDynamicStyles;
  document.addEventListener('DOMContentLoaded', () => applyDynamicStyles(document));
})();
