<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Criação de casas espíritas
    |--------------------------------------------------------------------------
    |
    | Quando desligado, a instalação atende uma única casa: só é possível criar
    | uma casa enquanto não existe nenhuma (primeira instalação), e a casa não
    | pode ser excluída. Ligue para hospedar várias casas na mesma instalação,
    | como numa federação ou na Llumo Cloud.
    |
    */

    'allow_center_creation' => (bool) env('LLUMO_ALLOW_CENTER_CREATION', false),

    /*
    |--------------------------------------------------------------------------
    | Cadastro aberto
    |--------------------------------------------------------------------------
    |
    | Quando desligado, só se cadastra quem tem um convite, exceto o primeiro
    | usuário de uma instalação vazia. Ligue para permitir que qualquer pessoa
    | crie uma conta, como na Llumo Cloud.
    |
    */

    'open_registration' => (bool) env('LLUMO_OPEN_REGISTRATION', false),

];
