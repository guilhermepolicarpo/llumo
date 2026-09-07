<?php

use App\Enums\BrazilianState;

test('options cover every state ordered by label', function () {
    $options = BrazilianState::options();

    expect($options)->toHaveCount(27);

    expect(array_column($options, 'value'))
        ->toEqualCanonicalizing(array_column(BrazilianState::cases(), 'value'));

    expect(array_column($options, 'label'))
        ->toBe([
            'Acre', 'Alagoas', 'Amapá', 'Amazonas', 'Bahia', 'Ceará', 'Distrito Federal',
            'Espírito Santo', 'Goiás', 'Maranhão', 'Mato Grosso', 'Mato Grosso do Sul',
            'Minas Gerais', 'Pará', 'Paraíba', 'Paraná', 'Pernambuco', 'Piauí',
            'Rio de Janeiro', 'Rio Grande do Norte', 'Rio Grande do Sul', 'Rondônia',
            'Roraima', 'Santa Catarina', 'São Paulo', 'Sergipe', 'Tocantins',
        ]);
});
