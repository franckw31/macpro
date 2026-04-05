(function(global){
  const defaults = {
    spacing: 60,      // horizontal space between suits (px)
    rowHeight: 80,    // visual height per row (px)
    fontSize: 60,     // suit size (px)
    opacity: 0.18,    // overall opacity of rows
    colors: { even: 'white', odd: 'red' }, // CSS class names defined in card-bg.css
    alternateColors: true, // if false, colors derive from suit (♠♣=white, ♥♦=red)
    suits: ['♠','♣','♥','♦'],
    staggerCycle: 4,  // number of steps for progressive offset per row
    containerId: 'card-bg'
  };

  function createContainer(id) {
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement('div');
      el.id = id;
      document.body.appendChild(el);
    }
    return el;
  }

  function clear(el) { el.innerHTML = ''; }

  function render(el, opts) {
    clear(el);
    const rows = Math.ceil(window.innerHeight / opts.rowHeight);
    const cols = Math.ceil(window.innerWidth / opts.spacing);

    // apply font size and opacity on row via inline style overrides if desired
    for (let r = 0; r < rows; r++) {
      const row = document.createElement('div');
      row.className = 'card-row';
      row.style.opacity = String(opts.opacity);

      // progressive offset to break vertical alignment
      const offsetPx = Math.round((r % opts.staggerCycle) * opts.spacing / opts.staggerCycle);
      row.style.transform = `translateX(${offsetPx}px)`;

      for (let c = 0; c < cols + 2; c++) {
        const suit = opts.suits[(r + c) % opts.suits.length];
        const span = document.createElement('span');
        span.className = 'suit';
        span.style.fontSize = `${opts.fontSize}px`;

        // color selection
        let colorClass;
        if (opts.alternateColors) {
          colorClass = ((r + c) % 2 === 0) ? opts.colors.even : opts.colors.odd;
        } else {
          colorClass = (suit === '♥' || suit === '♦') ? 'red' : 'white';
        }
        span.classList.add(colorClass);

        span.textContent = suit;
        row.appendChild(span);
      }

      el.appendChild(row);
    }
  }

  const CardBackground = {
    init(userOpts) {
      const opts = Object.assign({}, defaults, userOpts || {});
      const el = createContainer(opts.containerId);
      // ensure base styles are applied even if page lacks card-bg.css
      el.style.position = 'fixed';
      el.style.top = '0';
      el.style.left = '0';
      el.style.width = '100vw';
      el.style.height = '100vh';
      el.style.background = el.style.background || '#000';
      el.style.zIndex = el.style.zIndex || '-1';
      el.style.pointerEvents = 'none';

      function rerender(){ render(el, opts); }
      window.addEventListener('resize', rerender);
      rerender();
    },
    destroy(id) {
      const el = document.getElementById(id || defaults.containerId);
      if (!el) return;
      window.removeEventListener('resize', this.rerender);
      el.parentNode && el.parentNode.removeChild(el);
    }
  };

  global.CardBackground = CardBackground;
})(window);
