<footer>
            <div class="footer-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: nowrap;">
                <span class="text-bold text-uppercase"> C(2025) Franck W | Accueil | Répartition</span>
                <span id="current-time"></span>
                <span class="go-top"><i class="ti-angle-up"></i></span>
            </div>
        </footer>
        <script>
            function updateTime() {
                const now = new Date();
                const options = { 
                    year: 'numeric', 
                    month: '2-digit', 
                    day: '2-digit', 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit',
                    hour12: false
                };
                const timeString = now.toLocaleDateString('fr-FR', options);
                document.getElementById('current-time').textContent = timeString;
            }
            
            // Update time immediately and then every second
            updateTime();
            setInterval(updateTime, 1000);
        </script>

        <script>
        // MutationObserver: watch for late-inserted small fixed circular nodes and remove them.
        (function(){
            function isParasitic(el){
                try{
                    if (!(el instanceof Element)) return false;
                    var cs = window.getComputedStyle(el);
                    if (cs.position !== 'fixed') return false;
                    var rect = el.getBoundingClientRect();
                    if (!rect.width || !rect.height) return false;
                    if (Math.max(rect.width, rect.height) > 80) return false;
                    // ignore known containers
                    if (el.closest('footer, header, nav, .navbar, #footer, .app-footer')) return false;
                    var idcls = (el.id||'') + ' ' + (el.className||'');
                    if (/btn|button|footer|nav|submit|inscrire|cardevent|clock|rvNotification/i.test(idcls)) return false;
                    // must be visually circular-ish
                    var br = cs.borderRadius || '';
                    var brv = parseFloat(br) || 0;
                    if (!(brv >= Math.min(rect.width, rect.height)/4)) return false;
                    // skip if textual or contains meaningful content
                    if (el.textContent && el.textContent.trim().length>0) return false;
                    if (el.querySelector && (el.querySelector('img, svg, input, textarea, select'))) return false;
                    // location heuristic: near bottom area
                    if (rect.bottom < (window.innerHeight - 40)) return false;
                    return true;
                }catch(e){return false;}
            }

            function removeIfParasitic(node){
                if (isParasitic(node)){
                    node.style.display='none';
                    node.setAttribute('data-removed-by','mutation-hide-parasitic-fab');
                    return true;
                }
                return false;
            }

            // Scan existing nodes once quickly
            try{
                document.querySelectorAll('body *').forEach(function(n){ removeIfParasitic(n); });
            }catch(e){}

            var obs = new MutationObserver(function(mutations){
                for(var i=0;i<mutations.length;i++){
                    var mut = mutations[i];
                    if (mut.addedNodes && mut.addedNodes.length){
                        mut.addedNodes.forEach(function(n){
                            // check the node itself
                            if (removeIfParasitic(n)) return;
                            // check children quickly
                            if (n.querySelectorAll) {
                                n.querySelectorAll('*').forEach(function(c){ removeIfParasitic(c); });
                            }
                        });
                    }
                }
            });
            try{
                obs.observe(document.body, { childList: true, subtree: true });
            }catch(e){/* ignore */}
            // stop observing after 20s to limit side effects
            setTimeout(function(){ try{ obs.disconnect(); }catch(e){} }, 20000);
        })();
        </script>
        <style>
        /* Hide ResponsiveVoice permission popup on activity pages (parasitic) */
        .rvNotification { display: none !important; }
        </style>
        <script>
        // Unified handler for the bottom 'Répartition' control across pages.
        (function(){
            function handleRepartitionClick(e){
                var target = e.target.closest('#footerRepartition, .footer-repartition, [data-repartition]');
                if(!target) return;
                e.preventDefault();
                try {
                    var prize = document.getElementById('prizepool-section');
                    if (prize) {
                        prize.scrollIntoView({behavior: 'smooth', block: 'center'});
                        // make the clicked control appear active when visible
                        document.querySelectorAll('#footerRepartition, .footer-repartition, [data-repartition]').forEach(function(n){n.classList.remove('active');});
                        target.classList.add('active');
                    } else {
                        // If the section is not present, set hash and reload so server can render the section
                        if (location.hash !== '#prizepool-section') location.hash = '#prizepool-section';
                        location.reload();
                    }
                } catch (err) {
                    console.error('Répartition handler error', err);
                }
            }
            document.addEventListener('click', handleRepartitionClick, false);
        })();
        </script>
            <script>
            // Defensive: hide very small, fixed, circular, unlabeled elements that look like parasitic FABs.
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    var nodes = Array.from(document.querySelectorAll('div,button,a,span'));
                    nodes.forEach(function(el){
                        try {
                            var cs = window.getComputedStyle(el);
                            if (cs.position !== 'fixed') return;
                            var rect = el.getBoundingClientRect();
                            var w = rect.width, h = rect.height;
                            if (w === 0 || h === 0) return;
                            // size threshold for small FAB-like items
                            if (Math.max(w,h) > 80) return;
                            // ignore obvious footer/nav elements
                            var path = el.closest('footer, .navbar, #footer, .app-navbar, .app-footer, .bottom-nav');
                            if (path) return;
                            // ignore elements with button-like classes or ids
                            var idcls = (el.id||'') + ' ' + (el.className||'');
                            if (/btn|button|footer|nav|submit|inscrire|clock|clock|cardevent|footerRepartition/i.test(idcls)) return;
                            // check border-radius to detect circle
                            var br = cs.borderRadius || '';
                            var numericBR = parseFloat(br) || 0;
                            // consider it circular if border-radius is large relative to size
                            if (!(numericBR >= Math.min(w,h)/4)) return;
                            // skip if element has visible text or labeled children or images
                            if (el.textContent && el.textContent.trim().length>0) return;
                            if (el.querySelector('img, svg')) return;
                            // final guard: ensure element is near the bottom/right where user reported it
                            if (rect.bottom < (window.innerHeight - 40) && rect.right < (window.innerWidth/2)) return;
                            el.style.display = 'none';
                            el.setAttribute('data-removed-by','hide-parasitic-fab');
                        } catch(e){/* noop per element */}
                    });
                } catch(e){console.error('fab defender error', e);} 
            });
            </script>
        <script>
        // Hide tiny unlabeled fixed elements that can appear as a parasitic floating button
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Only run on activity view pages to limit side-effects
                if (!location.pathname.includes('voir-activite')) return;
                var els = document.querySelectorAll('button, a, div');
                els.forEach(function(el) {
                    var cs = window.getComputedStyle(el);
                    if (cs.position !== 'fixed') return;
                    var rect = el.getBoundingClientRect();
                    // small square-ish elements near the bottom are likely the parasite
                    if (rect.width <= 56 && rect.height <= 56 && rect.bottom > (window.innerHeight - 120)) {
                        if (!el.textContent.trim()) el.style.display = 'none';
                    }
                });
            } catch (e) {
                console.error('Floating-fab removal script error', e);
            }
        });
        </script>
