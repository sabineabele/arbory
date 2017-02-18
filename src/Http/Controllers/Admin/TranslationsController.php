<?php

namespace CubeSystems\Leaf\Http\Controllers\Admin;

use CubeSystems\Leaf\Breadcrumbs;
use CubeSystems\Leaf\Http\Requests\TranslationStoreRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;
use Waavi\Translation\Models\Language;
use Waavi\Translation\Models\Translation;
use Waavi\Translation\Repositories\LanguageRepository;
use Waavi\Translation\Repositories\TranslationRepository;

/**
 * Class TranslationsController
 * @package CubeSystems\Leaf\Http\Controllers\Admin
 */
class TranslationsController extends Controller
{
    /**
     * @var TranslationRepository
     */
    protected $translationsRepository;

    /**
     * @var LanguageRepository
     */
    protected $languagesRepository;

    /**
     * @var Request
     */
    protected $request;

    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     * @param TranslationRepository $translationRepository
     * @param LanguageRepository $languagesRepository
     */
    public function __construct( TranslationRepository $translationRepository, LanguageRepository $languagesRepository )
    {
        $this->translationsRepository = $translationRepository;
        $this->languagesRepository = $languagesRepository;
    }

    /**
     * @param Request $request
     * @return Factory|View
     */
    public function index( Request $request )
    {
        $this->request = $request;

        $languages = $this->languagesRepository->all();

        /** @noinspection PhpUndefinedMethodInspection */
        /* @var $allItems Builder */
        $allItems = Translation::distinct()->select( 'item', 'group', 'namespace' );

        $translationsQuery = \DB::table( \DB::raw( '(' . $allItems->toSql() . ') as d1' ) );

        $translationsQuery->addSelect( 'd1.*' );

        $translationsTableName = ( new Translation() )->getTable();

        foreach( $languages as $language )
        {
            $locale = $language->locale;

            $joinAlias = 'l_' . $locale;

            $translationsQuery->addSelect( $joinAlias . '.text AS ' . $locale . '_text' );
            $translationsQuery->addSelect( $joinAlias . '.locked AS ' . $locale . '_locked' );
            $translationsQuery->addSelect( $joinAlias . '.unstable AS ' . $locale . '_unstable' );

            $translationsQuery->leftJoin(
                $translationsTableName . ' as l_' . $locale,
                function ( JoinClause $join ) use ( $joinAlias, $locale )
                {
                    $join
                        ->on( $joinAlias . '.group', '=', 'd1.group' )
                        ->on( $joinAlias . '.item', '=', 'd1.item' )
                        ->on( $joinAlias . '.locale', '=', \DB::raw( '\'' . $locale . '\'' ) );
                }
            );
        }

        $searchString = $request->get( 'search' );

        if( $searchString )
        {
            $translationsQuery->where( 'd1.group', 'LIKE', '%' . $searchString . '%' );
            $translationsQuery->orWhere( 'd1.namespace', 'LIKE', '%' . $searchString . '%' );
            $translationsQuery->orWhere( 'd1.item', 'LIKE', '%' . $searchString . '%' );

            foreach( $languages as $language )
            {
                $translationsQuery->orWhere( 'l_' . $language->locale . '.text', 'LIKE', '%' . $searchString . '%' );
            }
        }

        $paginatedItems = $this->getPaginatedItems( $translationsQuery );

        return view(
            'leaf::controllers.translations.index',
            [
                'breadcrumbs' => $this->getBreadcrumbs()->get(),
                'languages' => $languages,
                'translations' => $paginatedItems,
                'paginator' => $paginatedItems,
                'search' => $request->get( 'search' ),
                'hhh' => function ( $kek1 ) use ( $searchString )
                {
                    return str_replace( $searchString, '<span style="background-color: lime; font-weight:bold">' . htmlentities( $searchString ) . '</span>', htmlentities( $kek1 ) );
                }
            ]
        );
    }

    /**
     * @param Request $request
     * @param string $namespace
     * @param string $group
     * @param string $item
     * @return View
     */
    public function edit( Request $request, $namespace, $group, $item )
    {
        $this->request = $request;

        $breadcrumbs = $this->getBreadcrumbs();

        /* @var $languages Language[] */
        $languages = $this->languagesRepository->all();

        $translations = [];
        foreach( $languages as $language )
        {
            /** @noinspection PhpUndefinedFieldInspection */
            $locale = $language->locale;

            $translation = $this->translationsRepository->findByCode(
                $locale,
                $namespace,
                $group,
                $item
            );

            if( !$translation )
            {
                $translation = new Translation( [
                    'locale' => $locale,
                    'namespace' => $namespace,
                    'group' => $group,
                    'item' => $item,
                    'text' => $namespace . '::' . $group . '.' . $item
                ] );
                $translation->save();
            }

            $translations[$locale] = $translation;
        }

        return view(
            'leaf::controllers.translations.edit',
            [
                'input' => $request,
                'breadcrumbs' => $breadcrumbs->get(),
                'languages' => $languages,
                'namespace' => $namespace,
                'group' => $group,
                'item' => $item,
                'translations' => $translations,
                'back_to_index_url' => route( 'admin.translations.index', $this->getContext() ),
                'update_url' => route( 'admin.translations.update', $this->getContext() )
            ]
        );
    }

    /**
     * @param TranslationStoreRequest $request
     * @return RedirectResponse|Redirector
     */
    public function store( TranslationStoreRequest $request )
    {
        $this->request = $request;

        /* @var $languages Language[] */
        $languages = $this->languagesRepository->all();

        foreach( $languages as $language )
        {
            /** @noinspection PhpUndefinedFieldInspection */
            $locale = $language->locale;

            $translation = $this->translationsRepository->findByCode(
                $locale,
                $request->get( 'namespace' ),
                $request->get( 'group' ),
                $request->get( 'item' )
            );

            /** @noinspection PhpUndefinedFieldInspection */
            $this->translationsRepository->updateAndLock(
                $translation->id,
                $request->get( 'text_' . $locale )
            );
        }

        return redirect( route( 'admin.translations.index', $this->getContext() ) );
    }

    /**
     * @param \stdClass $item
     * @param LengthAwarePaginator $paginator
     * @return string
     */
    private function getEditUrl( $item, LengthAwarePaginator $paginator )
    {
        return route(
            'admin.translations.edit',
            [
                'namespace' => $item->namespace,
                'group' => $item->group,
                'item' => $item->item,
                'page' => $paginator->currentPage(),
                'search' => $this->request->get( 'search' )
            ] );
    }

    /**
     * @param Builder $translationsQueryBuilder
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getPaginatedItems( Builder $translationsQueryBuilder )
    {
        $paginator = $translationsQueryBuilder->paginate( 15 );

        foreach( $paginator->items() as $item )
        {
            $item->edit_url = $this->getEditUrl( $item, $paginator );
        }

        return $paginator;
    }

    /**
     * @return Breadcrumbs
     */
    private function getBreadcrumbs()
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->add(
            trans( 'leaf.breadcrumbs.home' ),
            route( 'admin.dashboard' )
        );
        $breadcrumbs->add(
            trans( 'leaf.translations.index' ),
            route( 'admin.translations.index' )
        );

        return $breadcrumbs;
    }

    /**
     * @return array
     */
    private function getContext()
    {
        return [ 'page' => $this->request->get( 'page' ), 'search' => $this->request->get( 'search' ) ];
    }
}