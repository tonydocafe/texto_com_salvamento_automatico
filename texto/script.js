let ultimaMensagemSalva = "";
let timeoutDigitação = null;
let salvando = false;

function atualizarStatus(msg, cor = "#666") {
    const status = document.getElementById('status');
    status.textContent = msg;
    status.style.color = cor;
}

async function salvarTexto() {
    const texto = document.getElementById("mensagem").value.trim();
    
    if (!texto || texto === ultimaMensagemSalva || salvando) return;

    salvando = true;
    atualizarStatus("Salvando...", "blue");

    try {
        const response = await fetch('salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'texto=' + encodeURIComponent(texto)
        });

        const resultado = await response.json();

        if (resultado.status === "sucesso") {
            ultimaMensagemSalva = texto;
            document.getElementById('resposta').textContent = "Último salvamento: " + new Date().toLocaleTimeString();
            atualizarStatus("Salvo!", "green");
        } else if (resultado.status === "ignorado") {
            atualizarStatus("Texto já salvo", "orange");
        } else {
            atualizarStatus("Erro: " + resultado.msg, "red");
        }
    } catch (error) {
        console.error('Erro:', error);
        atualizarStatus("Erro ao salvar", "red");
    } finally {
        salvando = false;
    }
}

// Aciona o salvamento 2 segundos após parar de digitar
document.getElementById('mensagem').addEventListener('input', () => {
    clearTimeout(timeoutDigitação);
    timeoutDigitação = setTimeout(salvarTexto, 2000);
});

// Salvamento manual
document.getElementById('botaoSalvar').addEventListener('click', salvarTexto);
