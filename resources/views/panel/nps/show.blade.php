<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zpětná vazba — Onhost.cz</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f5f7fa; }
        .nps-btn {
            width: 44px; height: 44px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 0.9rem;
            border: 2px solid #dee2e6;
            background: white;
            cursor: pointer;
            transition: all .15s;
        }
        .nps-btn:hover, .nps-btn.selected {
            border-color: #0d6efd;
            background: #0d6efd;
            color: white;
        }
        .nps-btn[data-score="0"],.nps-btn[data-score="1"],.nps-btn[data-score="2"],.nps-btn[data-score="3"],.nps-btn[data-score="4"],.nps-btn[data-score="5"],.nps-btn[data-score="6"] {
            border-color: #f8d7da;
        }
        .nps-btn[data-score="7"],.nps-btn[data-score="8"] { border-color: #fff3cd; }
        .nps-btn[data-score="9"],.nps-btn[data-score="10"] { border-color: #d1e7dd; }
        .nps-btn.selected[data-score="0"],.nps-btn.selected[data-score="1"],.nps-btn.selected[data-score="2"],
        .nps-btn.selected[data-score="3"],.nps-btn.selected[data-score="4"],.nps-btn.selected[data-score="5"],
        .nps-btn.selected[data-score="6"] { background: #dc3545; border-color: #dc3545; }
        .nps-btn.selected[data-score="7"],.nps-btn.selected[data-score="8"] { background: #ffc107; border-color: #ffc107; color: #212529; }
        .nps-btn.selected[data-score="9"],.nps-btn.selected[data-score="10"] { background: #198754; border-color: #198754; }
    </style>
</head>
<body>
<div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm" style="max-width:520px;width:100%">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="fw-bold fs-5 mb-1">Onhost.cz</div>
                <h1 class="h4">Doporučili byste nás?</h1>
                <p class="text-muted mb-0">Jak pravděpodobné je, že byste Onhost.cz doporučili přátelům nebo kolegům?</p>
            </div>

            <form action="{{ route('nps.submit', $nps->survey_token) }}" method="POST" id="nps-form">
                @csrf
                <input type="hidden" name="score" id="score-input" value="">

                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-center mb-2">
                        @for ($i = 0; $i <= 10; $i++)
                            <button type="button" class="nps-btn" data-score="{{ $i }}">{{ $i }}</button>
                        @endfor
                    </div>
                    <div class="d-flex justify-content-between text-muted" style="font-size:12px">
                        <span>😞 Vůbec ne</span>
                        <span>😊 Rozhodně ano</span>
                    </div>
                </div>

                <div class="mb-3" id="comment-block" style="display:none">
                    <label class="form-label text-muted small">Chcete nám něco vzkázat? <span class="text-muted">(volitelné)</span></label>
                    <textarea name="comment" class="form-control" rows="3" maxlength="2000" placeholder="Váš komentář..."></textarea>
                </div>

                <div class="d-grid" id="submit-block" style="display:none !important">
                    <button type="submit" class="btn btn-primary">Odeslat hodnocení</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.nps-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.nps-btn').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('score-input').value = this.dataset.score;
        document.getElementById('comment-block').style.display = 'block';
        document.getElementById('submit-block').style.display = 'block';
    });
});
</script>
</body>
</html>
