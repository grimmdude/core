<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Frontend;

use Flarum\Frontend\Driver\TitleDriverInterface;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * A view which renders a HTML skeleton for Flarum's frontend app.
 */
class Document implements Renderable
{
    /**
     * The title of the document, displayed in the <title> tag.
     *
     * @var null|string
     */
    public $title;

    /**
     * The language of the document, displayed as the value of the attribute `lang` in the <html> tag.
     *
     * @var null|string
     */
    public $language;

    /**
     * The text direction of the document, displayed as the value of the attribute `dir` in the <html> tag.
     *
     * @var null|string
     */
    public $direction;

    /**
     * The name of the frontend app view to display.
     *
     * @var string
     */
    public $appView = 'flarum::frontend.app';

    /**
     * The name of the frontend layout view to display.
     *
     * @var string
     */
    public $layoutView;

    /**
     * The name of the frontend content view to display.
     *
     * @var string
     */
    public $contentView = 'flarum::frontend.content';

    /**
     * The SEO content of the page, displayed within the layout in <noscript> tags.
     *
     * @var string|Renderable
     */
    public $content;

    /**
     * Other variables to preload into the Flarum JS.
     *
     * @var array
     */
    public $payload = [];

    /**
     * An array of meta tags to append to the page's <head>.
     *
     * @var array
     */
    public $meta = [];

    /**
     * The canonical URL for this page.
     *
     * This will signal to search engines what URL should be used for this
     * content, if it can be found under multiple addresses. This is an
     * important tool to tackle duplicate content.
     *
     * @var null|string
     */
    public $canonicalUrl;

    /**
     * An array of strings to append to the page's <head>.
     *
     * @var array
     */
    public $head = [];

    /**
     * An array of strings to prepend before the page's </body>.
     *
     * @var array
     */
    public $foot = [];

    /**
     * An array of JavaScript URLs to load.
     *
     * @var array
     */
    public $js = [];

    /**
     * An array of CSS URLs to load.
     *
     * @var array
     */
    public $css = [];

    /**
     * An array of preloaded assets.
     *
     * Each array item should be an array containing keys that pertain to the
     * `<link rel="preload">` tag.
     *
     * For example, the following will add a preload tag for a FontAwesome font file:
     * ```
     * $this->preloads[] = [
     *   'href' => '/assets/fonts/fa-solid-900.woff2',
     *   'as' => 'font',
     *   'type' => 'font/woff2',
     *   'crossorigin' => ''
     * ];
     * ```
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Link_types/preload
     *
     * @var array
     */
    public $preloads = [];

    /**
     * @var Factory
     */
    protected $view;

    /**
     * @var array
     */
    protected $forumApiDocument;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(Factory $view, array $forumApiDocument, Request $request)
    {
        $this->view = $view;
        $this->forumApiDocument = $forumApiDocument;
        $this->request = $request;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $this->view->share('forum', Arr::get($this->forumApiDocument, 'data.attributes'));

        return $this->makeView()->render();
    }

    /**
     * @return View
     */
    protected function makeView(): View
    {
        return $this->view->make($this->appView)->with([
            'title' => $this->makeTitle(),
            'payload' => $this->payload,
            'layout' => $this->makeLayout(),
            'language' => $this->language,
            'direction' => $this->direction,
            'js' => $this->makeJs(),
            'head' => $this->makeHead(),
            'foot' => $this->makeFoot(),
        ]);
    }

    /**
     * @return string
     */
    protected function makeTitle(): string
    {
        // @todo v2.0 inject as dependency instead
        return resolve(TitleDriverInterface::class)->makeTitle($this, $this->request, $this->forumApiDocument);
    }

    /**
     * @return View
     */
    protected function makeLayout(): View
    {
        if ($this->layoutView) {
            return $this->view->make($this->layoutView)->with('content', $this->makeContent());
        }
    }

    /**
     * @return View
     */
    protected function makeContent(): View
    {
        return $this->view->make($this->contentView)->with('content', $this->content);
    }

    protected function makePreloads(): array
    {
        return array_map(function ($preload) {
            $attributes = '';

            foreach ($preload as $key => $value) {
                $attributes .= " $key=\"".e($value).'"';
            }

            return "<link rel=\"preload\"$attributes>";
        }, $this->preloads);
    }

    /**
     * @return string
     */
    protected function makeHead(): string
    {
        $head = array_map(function ($url) {
            return '<link rel="stylesheet" href="'.e($url).'">';
        }, $this->css);

        if ($this->canonicalUrl) {
            $head[] = '<link rel="canonical" href="'.e($this->canonicalUrl).'">';
        }

        $head = array_merge($head, $this->makePreloads());

        $head = array_merge($head, array_map(function ($content, $name) {
            return '<meta name="'.e($name).'" content="'.e($content).'">';
        }, $this->meta, array_keys($this->meta)));

        return implode("\n", array_merge($head, $this->head));
    }

    /**
     * @return string
     */
    protected function makeJs(): string
    {
        return implode("\n", array_map(function ($url) {
            return '<script src="'.e($url).'"></script>';
        }, $this->js));
    }

    /**
     * @return string
     */
    protected function makeFoot(): string
    {
        return implode("\n", $this->foot);
    }

    /**
     * @return array
     */
    public function getForumApiDocument(): array
    {
        return $this->forumApiDocument;
    }

    /**
     * @param array $forumApiDocument
     */
    public function setForumApiDocument(array $forumApiDocument)
    {
        $this->forumApiDocument = $forumApiDocument;
    }
}
