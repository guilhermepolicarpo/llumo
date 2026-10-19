<?php

namespace App\Concerns;

use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * Remember the page the visitor came from, passed as the "from" query parameter, so "back" can return there.
 */
trait InteractsWithReturnUrl
{
    /**
     * The path of the page the visitor came from, or an empty string when they did not come from a page of this app.
     */
    #[Locked]
    #[Url]
    public string $from = '';

    /**
     * Get where "back" leads when the visitor did not come from a page of this app.
     */
    abstract protected function defaultBackUrl(): string;

    /**
     * Get the label of the "back" button when it leads to the default page.
     */
    abstract protected function defaultBackLabel(): string;

    /**
     * Ignore an origin that is not a path of this app, such as another site.
     */
    public function mountInteractsWithReturnUrl(): void
    {
        if (preg_match('~^/(?!/)[^\\\\\x00-\x20\x7F]*$~', $this->from) !== 1) {
            $this->from = '';
        }
    }

    /**
     * Get where "back" leads: the page the visitor came from, or the default page when there is none.
     */
    public function backUrl(): string
    {
        return $this->from !== '' ? $this->from : $this->defaultBackUrl();
    }

    /**
     * Get the label of the "back" button, which names the page only when it is the default one.
     */
    public function backLabel(): string
    {
        return $this->from !== '' ? __('Back') : $this->defaultBackLabel();
    }

    /**
     * Get the query parameters that carry the page the visitor came from on to another page.
     *
     * @return array{from?: string}
     */
    public function returnUrlParameters(): array
    {
        return $this->from !== '' ? ['from' => $this->from] : [];
    }
}
