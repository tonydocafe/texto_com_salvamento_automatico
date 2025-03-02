# README
#### Implementação de Salvamento Automático
##### Descrição
Este código implementa um sistema de salvamento automático para uma aplicação web. Ele é composto por duas partes: um trecho PHP no servidor e um código JavaScript no cliente. O salvamento automático é realizado a cada 60 segundos sem a necessidade de o usuário clicar em um botão de salvar.




#### Funcionamento
#### PHP
O código PHP verifica se o parâmetro autosave foi enviado no pedido. Caso o parâmetro seja verdadeiro, o código executa o método process_attempt() no objeto $attemptobj, o qual é responsável por processar e salvar os dados da tentativa.
A função process_attempt() é chamada com o timestamp atual (time()) e outros parâmetros, além da variável $page (que pode ser relacionada a uma página de formulário ou contexto de tentativa).
Após o processamento, o código responde com um JSON contendo o status do salvamento, informando ao cliente que a tentativa foi salva.
#### JavaScript
O JavaScript é responsável por realizar a chamada automática ao servidor para salvar os dados sem a interação direta do usuário.
A função autoSave() coleta os dados do formulário atual e os envia via AJAX para o servidor. Ela adiciona o parâmetro autosave=1 aos dados do formulário para indicar que se trata de uma tentativa de salvamento automático.
A função é chamada a cada 60 segundos usando setInterval(), o que garante que os dados sejam salvos periodicamente.
#### Como funciona a comunicação entre o cliente e o servidor?
O JavaScript utiliza fetch() para enviar os dados via POST para a mesma URL da página, incluindo os dados do formulário e o parâmetro de salvamento automático.
O servidor processa a requisição e, caso o parâmetro autosave seja detectado, ele salva os dados e envia uma resposta JSON.
O cliente recebe a resposta e exibe um log no console, indicando que os dados foram salvos automaticamente.
#### Configuração
Este sistema de salvamento automático pode ser configurado para salvar qualquer tipo de dado de formulário periodicamente. Para modificar o intervalo do salvamento automático, altere o valor de 60000 (milissegundos) no setInterval().

#### Requisitos
A página deve conter um formulário válido.
O código PHP deve ser executado em um ambiente que suporte a função optional_param(), como o Moodle, já que a função é específica desse sistema.
#### Considerações Finais
Esse sistema de salvamento automático é útil em aplicações que exigem que o usuário não perca seu progresso em formulários longos, como em testes ou submissões de conteúdo.




Salvar Texto Automaticamente com XAMPP
Este projeto permite salvar automaticamente o conteúdo digitado em um campo de texto para um banco de dados MySQL a cada 2 segundos após a digitação. O sistema utiliza XAMPP para configurar o ambiente local com Apache, PHP e MySQL.

📌 Requisitos
XAMPP instalado e em execução
Banco de dados MySQL configurado
Navegador compatível com JavaScript
🛠️ Configuração
1️⃣ Iniciar o XAMPP
Abra o XAMPP Control Panel e inicie os serviços:
Apache (para executar o PHP)
MySQL (para armazenar os dados)
2️⃣ Criar o Banco de Dados
Acesse o phpMyAdmin (http://localhost/phpmyadmin/) e execute:

sql
Copiar
Editar
CREATE DATABASE texto;
USE texto;

CREATE TABLE mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    texto TEXT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
3️⃣ Configurar os Arquivos
Coloque os seguintes arquivos dentro da pasta do seu servidor local (htdocs no XAMPP):

index.html → Interface do usuário
script.js → Lógica para salvar automaticamente
salvar.php → Script PHP para salvar os dados no banco
⚠ Certifique-se de que o XAMPP está rodando e que os arquivos estão acessíveis via http://localhost/seu-projeto/

🚀 Como Funciona
O usuário digita no campo de texto
O script salva automaticamente a cada 2 segundos após a digitação
O PHP verifica se a mensagem já foi salva para evitar duplicação
O status do salvamento é exibido na tela
🔧 Solução de Problemas
Erro de conexão: Verifique se o MySQL está rodando no XAMPP
Banco de dados não encontrado: Certifique-se de criar o banco conforme as instruções
PHP não está rodando: Acesse http://localhost/seu-projeto/salvar.php e veja se há erros
📌 Tecnologias Usadas
XAMPP (Apache, MySQL, PHP)
HTML, CSS, JavaScript
Fetch API para requisições assíncronas

