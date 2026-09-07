<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum BrazilianState: string
{
    case Acre = 'AC';
    case Alagoas = 'AL';
    case Amapa = 'AP';
    case Amazonas = 'AM';
    case Bahia = 'BA';
    case Ceara = 'CE';
    case DistritoFederal = 'DF';
    case EspiritoSanto = 'ES';
    case Goias = 'GO';
    case Maranhao = 'MA';
    case MatoGrosso = 'MT';
    case MatoGrossoDoSul = 'MS';
    case MinasGerais = 'MG';
    case Para = 'PA';
    case Paraiba = 'PB';
    case Parana = 'PR';
    case Pernambuco = 'PE';
    case Piaui = 'PI';
    case RioDeJaneiro = 'RJ';
    case RioGrandeDoNorte = 'RN';
    case RioGrandeDoSul = 'RS';
    case Rondonia = 'RO';
    case Roraima = 'RR';
    case SantaCatarina = 'SC';
    case SaoPaulo = 'SP';
    case Sergipe = 'SE';
    case Tocantins = 'TO';

    /**
     * Get the display label for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Acre => 'Acre',
            self::Alagoas => 'Alagoas',
            self::Amapa => 'Amapá',
            self::Amazonas => 'Amazonas',
            self::Bahia => 'Bahia',
            self::Ceara => 'Ceará',
            self::DistritoFederal => 'Distrito Federal',
            self::EspiritoSanto => 'Espírito Santo',
            self::Goias => 'Goiás',
            self::Maranhao => 'Maranhão',
            self::MatoGrosso => 'Mato Grosso',
            self::MatoGrossoDoSul => 'Mato Grosso do Sul',
            self::MinasGerais => 'Minas Gerais',
            self::Para => 'Pará',
            self::Paraiba => 'Paraíba',
            self::Parana => 'Paraná',
            self::Pernambuco => 'Pernambuco',
            self::Piaui => 'Piauí',
            self::RioDeJaneiro => 'Rio de Janeiro',
            self::RioGrandeDoNorte => 'Rio Grande do Norte',
            self::RioGrandeDoSul => 'Rio Grande do Sul',
            self::Rondonia => 'Rondônia',
            self::Roraima => 'Roraima',
            self::SantaCatarina => 'Santa Catarina',
            self::SaoPaulo => 'São Paulo',
            self::Sergipe => 'Sergipe',
            self::Tocantins => 'Tocantins',
        };
    }

    /**
     * Get all the states as selectable options, ordered by label.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $state) => ['value' => $state->value, 'label' => $state->label()])
            ->sortBy(fn (array $option) => Str::lower(Str::ascii($option['label'])))
            ->values()
            ->toArray();
    }
}
