<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation de vote</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #4cafef, #1976d2);
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            text-align: center;
            max-width: 450px;
            width: 100%;
        }

        h2 {
            color: #1976d2;
            margin-bottom: 20px;
        }

        .btn {
            background: #1976d2;
            color: #fff;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s ease;
        }

        .btn:disabled {
            background: #a0a0a0;
            cursor: not-allowed;
        }

        .btn:hover:enabled {
            background: #125a9c;
        }

        .loading {
            margin-top: 15px;
            color: #1976d2;
            font-weight: bold;
        }

        .success {
            color: #2e7d32;
            font-weight: bold;
            margin-top: 15px;
        }

        .error {
            color: #d32f2f;
            font-weight: bold;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>{{ $message }}</h2>

        @if(!$error && $transactionId)
            <button id="confirmBtn" class="btn">✅ Confirmer mon vote</button>
            <div id="status"></div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const btn = document.getElementById("confirmBtn");
            const statusDiv = document.getElementById("status");

            if(btn){
                btn.addEventListener("click", function() {
                    // Désactiver définitivement le bouton après clic
                    btn.disabled = true;
                    statusDiv.innerHTML = "<p class='loading'>⏳ Confirmation en cours...</p>";

                    axios.post("{{ route('vote.confirm') }}", {
                        transaction_id: "{{ $transactionId }}"
                    }).then(res => {
                        statusDiv.innerHTML = "<p class='success'>" + res.data.message + "</p>";

                        // Redirection automatique après 2s vers l'accueil
                        setTimeout(() => {
                            window.location.href = "{{ url('http://localhost:5173') }}";
                        }, 2000);

                    }).catch(err => {
                        statusDiv.innerHTML = "<p class='error'>❌ Erreur : " + (err.response?.data?.error || "Erreur serveur") + "</p>";

                        // Redirection vers une page erreur après 2s
                        setTimeout(() => {
                            window.location.href = "{{ url('http://localhost:5173') }}";
                        }, 2000);
                    });
                });
            }
        });
    </script>
</body>
</html>
