<?php

namespace Database\Seeders;

use App\Actions\Library\CreateBook;
use App\Actions\Library\UpdateLibrarySettings;
use App\Enums\BookCopyStatus;
use App\Models\AssistedPerson;
use App\Models\Author;
use App\Models\BookCategory;
use App\Models\BookCollection;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Publisher;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class LibrarySeeder extends Seeder
{
    /**
     * The library's books: their incarnate authors (who wrote or psychographed them), their spiritual authors
     * (the spirits who dictated them), publisher, category and collection with its volume.
     *
     * @var list<array{title: string, incarnate: list<string>, spiritual?: list<string>, publisher: string, category: string,
     *                 collection?: string, volume?: int, year: int, isbn?: string, synopsis?: string}>
     */
    private const array BOOKS = [
        ['title' => 'O Livro dos Espíritos', 'incarnate' => ['Allan Kardec'], 'publisher' => 'FEB', 'category' => 'Obras básicas', 'collection' => 'Pentateuco Kardequiano', 'volume' => 1, 'year' => 1857,
            'synopsis' => 'Os princípios da Doutrina Espírita sobre a imortalidade da alma, a natureza dos Espíritos e suas relações com os homens, as leis morais, a vida presente, a vida futura e o porvir da humanidade.'],
        ['title' => 'O Livro dos Médiuns', 'incarnate' => ['Allan Kardec'], 'publisher' => 'FEB', 'category' => 'Obras básicas', 'collection' => 'Pentateuco Kardequiano', 'volume' => 2, 'year' => 1861,
            'synopsis' => 'Guia dos médiuns e dos evocadores: o ensino especial dos Espíritos sobre a teoria de todos os gêneros de manifestações e os meios de comunicação com o mundo invisível.'],
        ['title' => 'O Evangelho Segundo o Espiritismo', 'incarnate' => ['Allan Kardec'], 'publisher' => 'FEB', 'category' => 'Obras básicas', 'collection' => 'Pentateuco Kardequiano', 'volume' => 3, 'year' => 1864,
            'synopsis' => 'A explicação das máximas morais do Cristo, sua concordância com o Espiritismo e sua aplicação às diversas circunstâncias da vida.'],
        ['title' => 'O Céu e o Inferno', 'incarnate' => ['Allan Kardec'], 'publisher' => 'FEB', 'category' => 'Obras básicas', 'collection' => 'Pentateuco Kardequiano', 'volume' => 4, 'year' => 1865],
        ['title' => 'A Gênese', 'incarnate' => ['Allan Kardec'], 'publisher' => 'FEB', 'category' => 'Obras básicas', 'collection' => 'Pentateuco Kardequiano', 'volume' => 5, 'year' => 1868],
        ['title' => 'O Que é o Espiritismo', 'incarnate' => ['Allan Kardec'], 'publisher' => 'IDE', 'category' => 'Estudo doutrinário', 'year' => 1859],
        ['title' => 'Nosso Lar', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['André Luiz'], 'publisher' => 'FEB', 'category' => 'Romance espírita', 'collection' => 'A Vida no Mundo Espiritual', 'volume' => 1, 'year' => 1944, 'isbn' => '9788573289442',
            'synopsis' => "André Luiz, médico que viveu no Rio de Janeiro, narra o que encontrou ao despertar no mundo espiritual: os anos de sofrimento nas regiões inferiores e o socorro que o levou à colônia Nosso Lar.\n\nNa cidade espiritual ele conhece a organização das moradias, dos ministérios e do trabalho, aprende o valor da prece e do serviço ao próximo e reencontra afetos da última existência.\n\nPrimeiro livro da série ditada a Francisco Cândido Xavier, a obra abre caminho para o estudo da vida além da morte com a linguagem de quem a viveu, mostrando que cada um colhe o que semeou e que a misericórdia divina oferece sempre novas oportunidades de renovação."],
        ['title' => 'Os Mensageiros', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['André Luiz'], 'publisher' => 'FEB', 'category' => 'Romance espírita', 'collection' => 'A Vida no Mundo Espiritual', 'volume' => 2, 'year' => 1944],
        ['title' => 'Missionários da Luz', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['André Luiz'], 'publisher' => 'FEB', 'category' => 'Romance espírita', 'collection' => 'A Vida no Mundo Espiritual', 'volume' => 3, 'year' => 1945],
        ['title' => 'Evolução em Dois Mundos', 'incarnate' => ['Francisco Cândido Xavier', 'Waldo Vieira'], 'spiritual' => ['André Luiz'], 'publisher' => 'FEB', 'category' => 'Estudo doutrinário', 'year' => 1958],
        ['title' => 'Paulo e Estêvão', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['Emmanuel'], 'publisher' => 'FEB', 'category' => 'Romance espírita', 'year' => 1941,
            'synopsis' => 'Episódios históricos do cristianismo primitivo: a conversão de Saulo de Tarso às portas de Damasco e o martírio de Estêvão.'],
        ['title' => 'Há Dois Mil Anos', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['Emmanuel'], 'publisher' => 'FEB', 'category' => 'Romance espírita', 'year' => 1939],
        ['title' => 'Caminho, Verdade e Vida', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['Emmanuel'], 'publisher' => 'FEB', 'category' => 'Mensagens', 'collection' => 'Fonte Viva', 'volume' => 1, 'year' => 1948],
        ['title' => 'Pão Nosso', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['Emmanuel'], 'publisher' => 'FEB', 'category' => 'Mensagens', 'collection' => 'Fonte Viva', 'volume' => 2, 'year' => 1950],
        ['title' => 'Vinha de Luz', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['Emmanuel'], 'publisher' => 'FEB', 'category' => 'Mensagens', 'collection' => 'Fonte Viva', 'volume' => 3, 'year' => 1952],
        ['title' => 'Fonte Viva', 'incarnate' => ['Francisco Cândido Xavier'], 'spiritual' => ['Emmanuel'], 'publisher' => 'FEB', 'category' => 'Mensagens', 'collection' => 'Fonte Viva', 'volume' => 4, 'year' => 1956],
        ['title' => 'Plenitude', 'incarnate' => ['Divaldo Pereira Franco'], 'spiritual' => ['Joanna de Ângelis'], 'publisher' => 'LEAL', 'category' => 'Estudo doutrinário', 'year' => 1990],
        ['title' => 'Violetas na Janela', 'incarnate' => ['Vera Lúcia Marinzeck de Carvalho'], 'spiritual' => ['Patrícia'], 'publisher' => 'Petit', 'category' => 'Romance espírita', 'year' => 1993],
        ['title' => 'Depois da Morte', 'incarnate' => ['Léon Denis'], 'publisher' => 'FEB', 'category' => 'Estudo doutrinário', 'year' => 1890],
    ];

    /**
     * @param  Collection<int, AssistedPerson>  $assistedPeople
     * @param  Collection<int, int>  $memberIds
     */
    public function run(Team $team, Collection $assistedPeople, Collection $memberIds, CreateBook $createBook, UpdateLibrarySettings $updateLibrarySettings): void
    {
        $updateLibrarySettings->handle($team, ['loan_period_days' => Team::DEFAULT_LOAN_PERIOD_DAYS, 'loan_max_renewals' => 2, 'loan_max_active_per_person' => 3]);

        $books = collect(self::BOOKS);
        $authors = $this->catalog($team, Author::class, $books->flatMap(fn (array $book) => [...$book['incarnate'], ...$book['spiritual'] ?? []]));
        $publishers = $this->catalog($team, Publisher::class, $books->pluck('publisher'));
        $categories = $this->catalog($team, BookCategory::class, $books->pluck('category'));
        $collections = $this->catalog($team, BookCollection::class, $books->pluck('collection'));

        foreach (self::BOOKS as $book) {
            $createBook->handle($team, [
                'title' => $book['title'],
                'isbn' => $book['isbn'] ?? null,
                'published_year' => $book['year'],
                'synopsis' => $book['synopsis'] ?? null,
                'publisher_id' => $publishers[$book['publisher']],
                'book_category_id' => $categories[$book['category']],
                'book_collection_id' => isset($book['collection']) ? $collections[$book['collection']] : null,
                'collection_volume' => $book['volume'] ?? null,
            ], [
                'incarnate' => array_map(fn (string $name) => $authors[$name], $book['incarnate']),
                'spiritual' => array_map(fn (string $name) => $authors[$name], $book['spiritual'] ?? []),
            ], fake()->numberBetween(1, 3));
        }

        $copies = $team->bookCopies()->get()->shuffle();

        $copies->shift()?->update(['status' => BookCopyStatus::Lost]);
        $copies->shift()?->update(['status' => BookCopyStatus::Withdrawn]);

        $this->seedLoans($team, $copies, $assistedPeople->shuffle(), $memberIds);
    }

    /**
     * Lend copies to the assisted people: two overdue, to show the lending block, some still out, and a
     * history of returned ones.
     *
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, AssistedPerson>  $borrowers
     * @param  Collection<int, int>  $memberIds
     */
    private function seedLoans(Team $team, Collection $copies, Collection $borrowers, Collection $memberIds): void
    {
        $lend = function (BookCopy $copy, AssistedPerson $borrower, int $daysAgo, array $attributes = []) use ($team, $memberIds): void {
            $loanedOn = today()->subDays($daysAgo);

            BookLoan::factory()->for($copy, 'copy')->for($borrower)->create([
                'team_id' => $team->id,
                'loaned_on' => $loanedOn,
                'due_on' => $loanedOn->addDays($team->loan_period_days),
                'registered_by' => $memberIds->random(),
                ...$attributes,
            ]);
        };

        $copies->splice(0, 2)->each(fn (BookCopy $copy) => $lend($copy, $borrowers->shift(), fake()->numberBetween(20, 35)));

        $copies->splice(0, 6)->each(fn (BookCopy $copy) => $lend($copy, $borrowers->shift(), fake()->numberBetween(0, 12), [
            'renewals_count' => fake()->numberBetween(0, 1),
        ]));

        $copies->take(8)->each(function (BookCopy $copy) use ($lend, $borrowers, $memberIds): void {
            $daysAgo = fake()->numberBetween(40, 180);

            $lend($copy, $borrowers->random(), $daysAgo, [
                'returned_on' => today()->subDays($daysAgo - fake()->numberBetween(5, 20)),
                'returned_to' => $memberIds->random(),
            ]);
        });
    }

    /**
     * Create one entry of a library catalog for each distinct name, returning their ids keyed by name.
     *
     * @param  class-string<Author|Publisher|BookCategory|BookCollection>  $model
     * @param  iterable<int, string|null>  $names
     * @return array<string, int>
     */
    private function catalog(Team $team, string $model, iterable $names): array
    {
        return $model::factory()->for($team)
            ->createMany(collect($names)->filter()->unique()->map(fn (string $name) => ['name' => $name])->values()->all())
            ->pluck('id', 'name')
            ->all();
    }
}
