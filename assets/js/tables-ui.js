(function() {
    // Initialization (runs on DOM ready)
    jQuery(document).ready(function () {
        if (typeof Main !== 'undefined' && Main.init) Main.init();
        if (typeof FormElements !== 'undefined' && FormElements.init) FormElements.init();

        if (window.CardBackground && window.CardBackground.init) {
            window.CardBackground.init({
                spacing: 60,
                rowHeight: 80,
                fontSize: 60,
                opacity: 0.18,
                alternateColors: true,
                colors: { even: 'white', odd: 'red' },
                suits: ['♠','♣','♥','♦'],
                staggerCycle: 4
            });
        }
    });

    // Main UI behaviours
    $(function() {
        const cfg = window.tablesConfig || {};
        const placementMode = cfg.mode || 'manual';
        const autoBalanceFlag = parseInt(cfg.autoBalance || 0, 10);

        // Local state
        let selectedSeat    = null;
        let selectedRowTap  = null;
        let draggedSeat     = null;
        let draggedRow      = null;
        let touchDragSeat   = null;
        let seatDropHappened = false;

        let touchStartX = 0;
        let touchStartY = 0;
        let touchMoved = false;
        const TOUCH_DRAG_THRESHOLD = 10; // px

        let rowTouchDrag = null;
        let rowTouchStartX = 0;
        let rowTouchStartY = 0;
        let rowTouchMoved = false;
        const ROW_TOUCH_DRAG_THRESHOLD = 10; // px

        function clearAutoSuggestions() { $('.seat').removeClass('suggest-from suggest-to'); }
        function clearSeatSelection() { if (selectedSeat) { selectedSeat.classList.remove('seat-selected'); selectedSeat = null; } }
        function clearRowSelection() { if (selectedRowTap) { selectedRowTap.classList.remove('row-selected'); selectedRowTap = null; } }

        $('#reset-auto-suggestion').on('click', function() { $('#auto-suggestion-text').text(''); $('#auto-suggestion-alert').hide(); clearAutoSuggestions(); });

        // assignRowToSeat, swapSeats, removeSeatAndUpdate, eliminateSeatPermanently, updateSeatOnServer
        // (kept the same logic as before, adapted to use cfg where needed)
        function assignRowToSeat(row, toSeat) {
            if (placementMode !== 'manual') return;
            const pid = row.dataset.participationId;
            if (!pid) return;
            const toPid = toSeat.dataset.participationId || '';
            if (toPid) return;
            const pseudo = $(row).find('td').eq(1).text();

            $('.seat[data-participation-id="' + pid + '"]').each(function() {
                const s = this;
                if (s === toSeat) return;
                s.dataset.participationId = '';
                $(s).find('.seat-name').text('Libre');
                $(s).find('.seat-rank').text('Libre');
                s.classList.add('empty');
                s.removeAttribute('draggable');
                $.post('update_seat.php', { id_participation: pid, table_no: 0, seat_no: 0 });
            });

            toSeat.dataset.participationId = pid;
            $(toSeat).find('.seat-name').text(pseudo || '');
            $(toSeat).find('.seat-rank').text('');
            toSeat.classList.remove('empty');
            toSeat.setAttribute('draggable', 'true');

            updateSeatOnServer(toSeat);
        }

        function swapSeats(fromSeat, toSeat) {
            if (fromSeat === toSeat) return;
            const toPid  = toSeat.dataset.participationId || '';
            const fromPid = fromSeat.dataset.participationId || '';
            const fromName  = $(fromSeat).find('.seat-name').text();
            const toName    = $(toSeat).find('.seat-name').text();

            fromSeat.dataset.participationId = toPid;
            toSeat.dataset.participationId   = fromPid;

            $(fromSeat).find('.seat-name').text(toPid ? toName : 'Libre');
            $(toSeat).find('.seat-name').text(fromPid ? fromName : 'Libre');
            $(fromSeat).find('.seat-rank').text(toPid ? '' : 'Libre');
            $(toSeat).find('.seat-rank').text(fromPid ? '' : 'Libre');

            if (fromSeat.dataset.participationId) { fromSeat.classList.remove('empty'); fromSeat.setAttribute('draggable', 'true'); } else { fromSeat.classList.add('empty'); fromSeat.removeAttribute('draggable'); }
            if (toSeat.dataset.participationId)   { toSeat.classList.remove('empty');   toSeat.setAttribute('draggable', 'true'); } else { toSeat.classList.add('empty');   toSeat.removeAttribute('draggable'); }

            updateSeatOnServer(fromSeat);
            updateSeatOnServer(toSeat);
        }

        function removeSeatAndUpdate(seat) {
            const pid = seat.dataset.participationId;
            if (!pid) return;
            seat.dataset.participationId = '';
            $(seat).find('.seat-name').text('Libre');
            $(seat).find('.seat-rank').text('Libre');
            seat.classList.add('empty');
            seat.removeAttribute('draggable');
            $.post('update_seat.php', { id_participation: pid, table_no: 0, seat_no: 0 }).done(function() {
                const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]');
                if (row) { const tdTable = row.querySelector('.col-table'); const tdSeat  = row.querySelector('.col-seat'); if (tdTable) tdTable.textContent = '-'; if (tdSeat) tdSeat.textContent  = '-'; }
                if (placementMode === 'auto' && autoBalanceFlag === 1) { window.location.reload(); }
            });
        }

        function eliminateSeatPermanently(seat) {
            const pid = seat.dataset.participationId;
            if (!pid) return;
            seat.dataset.participationId = '';
            $(seat).find('.seat-name').text('Libre');
            $(seat).find('.seat-rank').text('Libre');
            seat.classList.add('empty');
            seat.removeAttribute('draggable');
            $.post('eliminate_player.php', { id_participation: pid }).done(function() {
                const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]');
                if (row) { const tdStatus = row.querySelector('td:nth-child(3)'); const tdTable  = row.querySelector('.col-table'); const tdSeat   = row.querySelector('.col-seat'); if (tdStatus) tdStatus.textContent = 'Elimine'; if (tdTable) tdTable.textContent  = '-'; if (tdSeat) tdSeat.textContent   = '-'; row.classList.add('row-selected'); }
                if (placementMode === 'auto' && autoBalanceFlag === 1) { window.location.reload(); }
            });
        }

        // The rest of the event handlers (drag/drop, touch, clicks) are identical to previous inline code
        // For brevity we reattach the handlers by copying the selectors and logic.

        $('.seat').on('dragstart', function(e) {
            const seat = this;
            const pid = seat.dataset.participationId;
            if (!pid) { e.preventDefault(); return; }
            draggedSeat = seat; seatDropHappened = false; if (e.originalEvent && e.originalEvent.dataTransfer) e.originalEvent.dataTransfer.effectAllowed = 'move';
        });

        $('.seat').on('dragover', function(e) { if (!draggedSeat && !draggedRow) return; if (draggedSeat && draggedSeat === this) return; e.preventDefault(); if (e.originalEvent && e.originalEvent.dataTransfer) e.originalEvent.dataTransfer.dropEffect = 'move'; });

        $('.seat').on('drop', function(e) { e.preventDefault(); const target = this; if (!draggedSeat && !draggedRow) return; seatDropHappened = true; if (draggedRow) { assignRowToSeat(draggedRow, target); } else if (draggedSeat) { swapSeats(draggedSeat, target); } });

        $('.poker-table-center').on('dragover', function(e) { if (!draggedSeat) return; e.preventDefault(); if (e.originalEvent && e.originalEvent.dataTransfer) e.originalEvent.dataTransfer.dropEffect = 'move'; });

        $('.poker-table-center').on('drop', function(e) { e.preventDefault(); if (!draggedSeat) return; const center = this; const tableCenter = center.dataset.table; const fromSeat = draggedSeat; const fromPid  = fromSeat.dataset.participationId || ''; if (!fromPid) return; if (tableCenter && fromSeat.dataset.table === tableCenter) { eliminateSeatPermanently(fromSeat); } });

        $('.seat').on('dragend', function() {
            if (draggedSeat && !seatDropHappened) {
                const seat = draggedSeat; const pid  = seat.dataset.participationId; if (pid) {
                    seat.dataset.participationId = ''; $(seat).find('.seat-name').text('Libre'); $(seat).find('.seat-rank').text('Libre'); seat.classList.add('empty'); seat.removeAttribute('draggable');
                    $.post('update_seat.php', { id_participation: pid, table_no: 0, seat_no: 0 }).done(function() {
                        const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]'); if (row) { const tdTable = row.querySelector('.col-table'); const tdSeat  = row.querySelector('.col-seat'); if (tdTable) tdTable.textContent = '-'; if (tdSeat)  tdSeat.textContent  = '-'; }
                        if (placementMode === 'auto' && autoBalanceFlag === 1) { window.location.reload(); }
                    });
                }
            }
            draggedSeat = null; draggedRow  = null; seatDropHappened = false;
        });

        if (placementMode === 'manual') {
            $('.players-list tbody').on('click', 'tr', function(e) {
                const row = this; const pid = row.dataset.participationId; if (!pid) return; if (selectedRowTap === row) { row.classList.remove('row-selected'); selectedRowTap = null; } else { clearRowSelection(); row.classList.add('row-selected'); selectedRowTap = row; }
            });
            $('.players-list tbody').on('touchstart', 'tr', function(e) { if (e.touches.length !== 1) return; const row = this; const pid = row.dataset.participationId; if (!pid) return; rowTouchDrag = row; rowTouchMoved = false; const t = e.touches[0]; rowTouchStartX = t.clientX; rowTouchStartY = t.clientY; });
        }

        $('.seat').on('click', function(e) {
            if (draggedSeat || draggedRow) return; const seat = this; const pid  = seat.dataset.participationId || '';
            if (selectedRowTap && placementMode === 'manual') { if (!pid) { assignRowToSeat(selectedRowTap, seat); clearRowSelection(); } return; }
            if (pid) { if (!selectedSeat) { selectedSeat = seat; seat.classList.add('seat-selected'); } else if (selectedSeat === seat) { removeSeatAndUpdate(seat); clearSeatSelection(); } else { swapSeats(selectedSeat, seat); clearSeatSelection(); } } else { if (selectedSeat) { swapSeats(selectedSeat, seat); clearSeatSelection(); } }
        });

        if (placementMode === 'manual') {
            $('.players-list tbody tr').attr('draggable', 'true');
            $('.players-list tbody').on('dragstart', 'tr', function(e) { const row = this; const pid = row.dataset.participationId; if (!pid) { e.preventDefault(); return; } draggedRow = row; if (e.originalEvent && e.originalEvent.dataTransfer) { e.originalEvent.dataTransfer.effectAllowed = 'move'; } });
        }

        $('.seat').on('touchstart', function(e) { if (e.touches.length !== 1) return; const seat = this; const pid  = seat.dataset.participationId || ''; if (!pid) return; touchDragSeat = seat; touchMoved = false; const t = e.touches[0]; touchStartX = t.clientX; touchStartY = t.clientY; });

        $(document).on('touchmove', function(e) { if (!touchDragSeat) return; if (!e.touches || e.touches.length === 0) return; const t = e.touches[0]; const dx = t.clientX - touchStartX; const dy = t.clientY - touchStartY; if (Math.abs(dx) > TOUCH_DRAG_THRESHOLD || Math.abs(dy) > TOUCH_DRAG_THRESHOLD) { touchMoved = true; e.preventDefault(); } });

        $(document).on('touchend', function(e) {
            if (!touchDragSeat) return; const originSeat = touchDragSeat; touchDragSeat = null; const touch = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : null; touchMoved = false; if (!touch) return; const targetEl = document.elementFromPoint(touch.clientX, touch.clientY); if (!targetEl) { removeSeatAndUpdate(originSeat); return; } const center = $(targetEl).closest('.poker-table-center')[0]; if (center && center.dataset.table === originSeat.dataset.table) { eliminateSeatPermanently(originSeat); return; } const targetSeat = $(targetEl).closest('.seat')[0]; if (!targetSeat) { removeSeatAndUpdate(originSeat); return; } swapSeats(originSeat, targetSeat);
        });

        $(document).on('touchmove', function(e) { if (!rowTouchDrag) return; if (!e.touches || e.touches.length === 0) return; const t = e.touches[0]; const dx = t.clientX - rowTouchStartX; const dy = t.clientY - rowTouchStartY; if (Math.abs(dx) > ROW_TOUCH_DRAG_THRESHOLD || Math.abs(dy) > ROW_TOUCH_DRAG_THRESHOLD) { rowTouchMoved = true; e.preventDefault(); } });

        $(document).on('touchend', function(e) {
            if (!rowTouchDrag) return; const originRow = rowTouchDrag; const moved = rowTouchMoved; rowTouchDrag = null; rowTouchMoved = false; const touch = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : null; if (!touch) return; if (!moved) return; const targetEl = document.elementFromPoint(touch.clientX, touch.clientY); if (!targetEl) return; const targetSeat = $(targetEl).closest('.seat')[0]; if (targetSeat) { assignRowToSeat(originRow, targetSeat); clearRowSelection(); }
        });

        function updateSeatOnServer(seat) {
            const pid = seat.dataset.participationId; if (!pid) return; const tableNo = seat.dataset.table; let seatNo = seat.dataset.seat;
            if (placementMode === 'manual') {
                const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]'); if (row) {
                    const tdTable = row.querySelector('.col-table'); const tdSeat  = row.querySelector('.col-seat'); const currentTable = tdTable && tdTable.textContent.trim() !== '-' && tdTable.textContent.trim() !== '' ? parseInt(tdTable.textContent.trim(), 10) : 0; const currentSeat  = tdSeat && tdSeat.textContent.trim() !== '-' && tdSeat.textContent.trim() !== '' ? parseInt(tdSeat.textContent.trim(), 10) : 0; const targetTable = tableNo ? parseInt(tableNo, 10) : 0; if (targetTable > 0) { if (currentTable === targetTable && currentSeat > 0) { seatNo = currentSeat; } else { let maxSeat = 0; const rows = document.querySelectorAll('.players-list tbody tr'); rows.forEach(function(r) { const t = r.querySelector('.col-table'); const s = r.querySelector('.col-seat'); if (!t || !s) return; const tVal = t.textContent.trim(); const sVal = s.textContent.trim(); if (tVal === String(targetTable) && sVal !== '-' && sVal !== '') { const sn = parseInt(sVal, 10); if (!isNaN(sn) && sn > maxSeat) maxSeat = sn; } }); seatNo = maxSeat + 1; } } }

            $.post('update_seat.php', { id_participation: pid, table_no: tableNo, seat_no: seatNo }).done(function() {
                const row = document.querySelector('.players-list tr[data-participation-id="' + pid + '"]'); if (row) { const tdTable = row.querySelector('.col-table'); const tdSeat  = row.querySelector('.col-seat'); if (tdTable) tdTable.textContent = tableNo; if (tdSeat)  tdSeat.textContent  = seatNo; }
                const $seatRank = $(seat).find('.seat-rank'); if (seatNo && parseInt(seatNo, 10) > 0) { $seatRank.text(parseInt(seatNo, 10)); } else { $seatRank.text('Libre'); }
            });
        }

        // Polling for payment changes
        (function monitoringPolling() {
            const selectedActivityId = parseInt(cfg.selectedActivityId || 0, 10);
            if (!selectedActivityId || selectedActivityId <= 0) return;
            const pollInterval = 5000;
            let lastKnownPaymentHash = cfg.paidStatusHash || '';

            function checkForPaymentChanges() {
                fetch('tables.php?id_activite=' + selectedActivityId + '&check_payments=1', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
                .then(response => response.text())
                .then(data => { if (data !== lastKnownPaymentHash && data.length > 0) { lastKnownPaymentHash = data; location.reload(); } })
                .catch(error => console.warn('Erreur polling paiements:', error));
            }

            setInterval(checkForPaymentChanges, pollInterval);
        })();
    });
})();
