@once
    <div class="modal fade" id="recModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header rec-modal-head text-white">
                    <h5 class="modal-title text-white"><i class="bx bx-bell me-2"></i> Aksi Rekomendasi</h5>
                    <span class="badge bg-white text-warning ms-2">Model Random Forest</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="recModalBody"></div>
            </div>
        </div>
    </div>

    @push('script')
    <script>
        (function () {
            const REC_URL = @json(url('/getRecommendations'));
            const rEsc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
            let recCache = null;

            function card(r) {
                const petak = (r.petak || []).map(p => `<span class="badge bg-label-dark me-1">${rEsc(p)}</span>`).join('');
                const prob = r.probability != null ? ` <span class="text-muted">(${Math.round(r.probability * 100)}%)</span>` : '';
                const reasons = Object.entries(r.reasons_by_petak || {})
                    .filter(([, list]) => list && list.length)
                    .map(([p, list]) => `<li><strong>${rEsc(p)}:</strong> ${list.map(rEsc).join('; ')}</li>`).join('');
                return `
                    <div class="alert alert-${rEsc(r.type)} d-flex align-items-start p-3 mb-3">
                        <i class="${rEsc(r.icon)} fs-4 me-3"></i>
                        <div>
                            <strong class="d-block">${rEsc(r.title)}${prob}</strong>
                            <div class="mb-1">${petak}</div>
                            <p class="mb-1 small">${rEsc(r.message)}</p>
                            ${reasons ? `<ul class="small mb-0 ps-3">${reasons}</ul>` : ''}
                        </div>
                    </div>`;
            }

            function render(btn, recs) {
                const status = JSON.parse(btn.dataset.status || '[]');
                let petakSet = btn.dataset.petak ? [btn.dataset.petak]
                    : [...new Set(status.map(s => (String(s.label).match(/^(Petak \d+)/) || [])[1]).filter(Boolean))];
                const list = (recs || []).filter(r => !petakSet.length || (r.petak || []).some(p => petakSet.includes(p)));
                const kondisi = status.length
                    ? '<ul class="mb-0 ps-3">' + status.map(s => `<li><strong>${rEsc(s.label)}</strong>${s.reason ? ' &mdash; ' + rEsc(s.reason) : ''}</li>`).join('') + '</ul>'
                    : `<span class="text-success"><i class="bx bx-check-circle"></i> ${rEsc(btn.dataset.okText || 'Normal, tidak ada nilai sensor yang melewati batas.')}</span>`;
                document.getElementById('recModalBody').innerHTML = `
                    <div class="rec-modal-row mb-3">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="text-muted small"><i class="bx bx-time-five"></i> ${rEsc(btn.dataset.date)}</span>
                            <span class="badge bg-${rEsc(btn.dataset.color)}">${rEsc(btn.dataset.label)}</span>
                        </div>
                        <div class="small">${kondisi}</div>
                    </div>
                    <h6 class="mb-2">Aksi rekomendasi${petakSet.length ? ' &middot; ' + petakSet.map(rEsc).join(', ') : ''}</h6>
                    ${list.length ? list.map(card).join('') : '<p class="text-muted small mb-0">Tidak ada aksi rekomendasi untuk petak ini saat ini.</p>'}
                    ${btn.dataset.extra ? `<div class="small text-muted mb-2">${rEsc(btn.dataset.extra)}</div>` : ''}
                    <small class="text-muted d-block">Aksi rekomendasi berdasarkan kondisi sensor & kamera terbaru.</small>`;
            }

            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.rec-open');
                if (!btn) return;
                const modalEl = document.getElementById('recModal');
                document.getElementById('recModalBody').innerHTML =
                    '<div class="d-flex align-items-center gap-2 text-muted small"><span class="spinner-border spinner-border-sm text-warning"></span> Memuat aksi rekomendasi...</div>';
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                if (recCache) { render(btn, recCache); return; }
                fetch(REC_URL, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(d => { recCache = d.recommendations || []; render(btn, recCache); })
                    .catch(() => { document.getElementById('recModalBody').innerHTML = '<p class="text-danger small mb-0">Gagal memuat aksi rekomendasi.</p>'; });
            });
        })();
    </script>
    @endpush

    <style>
        .rec-open { cursor: pointer; }
        .rec-open:hover { filter: brightness(.92); }
        .rec-modal-head { background: #ffab00; }
        .rec-modal-row { padding: 12px 14px; border-radius: 12px; background: #f7f9fb; border: 1px solid #edf0f4; }
    </style>
@endonce
