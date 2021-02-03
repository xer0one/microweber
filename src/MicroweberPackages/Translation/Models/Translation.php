<?php

namespace MicroweberPackages\Translation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use MicroweberPackages\Database\Traits\CacheableQueryBuilderTrait;

class Translation extends Model
{
    use CacheableQueryBuilderTrait;

    public $timestamps = true;

    /** @var array */
    public $translatable = ['translation_text'];

    /** @var array */
    public $guarded = ['id'];

    public static function getNamespaces()
    {
        $queryModel = static::query();
        $queryModel->groupBy('translation_namespace');

        return $queryModel->get();
    }

    public static function getGroupedTranslations($filter = [])
    {
        if (!isset($filter['page'])) {
            $filter['page'] = 1;
        }

        if (!isset($filter['translation_namespace'])) {
            $filter['translation_namespace'] = '*';
        }

        $queryModel = static::query();
        $queryModel->where('translation_namespace', $filter['translation_namespace']);
        $queryModel->groupBy(\DB::raw("MD5(translation_key)"));

        if (isset($filter['search']) && !empty($filter['search'])) {
            $queryModel->where(function($subQuery) use ($filter) {
                $subQuery->where('translation_key', 'like', '%' . $filter['search'] . '%');
                $subQuery->orWhere('translation_text', 'like', '%' . $filter['search'] . '%');
            });
        }

        Paginator::currentPageResolver(function() use ($filter) {
            return $filter['page'];
        });

        $getTranslations = $queryModel->paginate(50);
        $pagination = $getTranslations->links("pagination::bootstrap-4");

        $group = [];

        foreach ($getTranslations as $translation) {

            $translationLocales = [];
            $getTranslationLocales = static::where('translation_key', $translation->translation_key)->get()->toArray();
            foreach ($getTranslationLocales as $translationLocale) {
                $translationLocales[$translationLocale['translation_locale']] = $translationLocale['translation_text'];
            }

            $group[$translation->translation_key] = $translationLocales;
        }

        return ['results'=>$group,'pagination'=>$pagination];

    }
}
