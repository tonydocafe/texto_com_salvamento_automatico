# 📝 Salvar Texto Automaticamente com XAMPP  

Este projeto permite salvar automaticamente um texto digitado em um campo de entrada para um banco de dados **MySQL** usando **PHP** e **JavaScript**. O salvamento ocorre a cada **2 segundos após a última digitação**.  

O sistema utiliza **XAMPP** para fornecer um ambiente local com **Apache, PHP e MySQL**.  

## 📌 Requisitos  

- **XAMPP** instalado  
- **Apache e MySQL ativados no XAMPP**  
- **Banco de dados MySQL configurado**  

## 🛠️ Configuração  

### 1️⃣ Iniciar o XAMPP  
1. Abra o **XAMPP Control Panel**  
2. Inicie o **Apache e o MySQL**  

### 2️⃣ Criar o Banco de Dados  
1. Acesse **phpMyAdmin** em:  
http://localhost/phpmyadmin/

PHP
----------------------------------------------------------------------------
O script PHP recebe uma requisição do tipo POST, verifica se há um campo chamado texto na requisição e tenta armazenar esse conteúdo em um banco de dados MySQL, garantindo que não haja duplicatas.

.JS
------------------------------------------------------------------------------
JavaScript implementa um sistema de salvamento automático e manual de um texto digitado pelo usuário, enviando-o para um servidor via fetch(). Ele evita envios repetidos e fornece feedback visual ao usuário.
