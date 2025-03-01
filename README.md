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
