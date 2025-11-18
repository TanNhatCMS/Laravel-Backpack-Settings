<?php

namespace Backpack\Settings\app\Http\Controllers;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Settings\app\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use JsonException;
use Prologue\Alerts\Facades\Alert;

class SettingCrudController extends CrudController
{
    use ListOperation;
    use UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(config('backpack.settings.model', Setting::class));
        CRUD::setEntityNameStrings(trans('backpack::settings.setting_singular'), trans('backpack::settings.setting_plural'));
        CRUD::setRoute(backpack_url(config('backpack.settings.route')));
    }

    /**
     * Define which routes are needed for this operation.
     *
     * @param $segment
     * @param string $routeName Prefix of the route name.
     * @param string $controller Name of the current CrudController.
     */
    protected function setupUpdateRoutes($segment, $routeName, $controller): void
    {
        Route::get($segment . '/{id}/edit', [
            'as' => $routeName . '.edit',
            'uses' => $controller . '@edit',
            'operation' => 'update',
        ]);

        Route::put($segment . '/{id}', [
            'as' => $routeName . '.update',
            'uses' => $controller . '@update',
            'operation' => 'update',
        ]);

        Route::get($segment . '/key/{key}/edit', [
            'as' => $routeName . '.editKey',
            'uses' => $controller . '@editKey',
            'operation' => 'updateKey',
        ]);

        Route::put($segment . '/key/{key}', [
            'as' => $routeName . '.updateKey',
            'uses' => $controller . '@updateKey',
            'operation' => 'updateKey',
        ]);

        Route::get($segment . '/group/{group}/edit', [
            'as' => $routeName . '.editGroup',
            'uses' => $controller . '@editGroup',
            'operation' => 'updateGroup',
        ]);

        Route::put($segment . '/group/{group}', [
            'as' => $routeName . '.updateGroup',
            'uses' => $controller . '@updateGroup',
            'operation' => 'updateGroup',
        ]);
    }

    public function setupListOperation(): void
    {
        // only show settings which are marked as active
        CRUD::addClause('where', 'active', 1);

        // columns to show in the table view
        CRUD::setColumns([
            [
                'name' => 'name',
                'label' => trans('backpack::settings.name'),
            ],
            [
                'name' => 'value',
                'label' => trans('backpack::settings.value'),
            ],
//            [
//                'name'  => 'description',
//                'label' => trans('backpack::settings.description'),
//            ],
            [
                'name' => 'key',
                'label' => trans('backpack::settings.key'),
            ],
        ]);
        $this->crud->addFilter(
            [
                'type'  => 'select',
                'name'  => 'group',
                'label' => 'Phân loại',
            ],
            Setting::where('active', true)->pluck('group')->unique()->values()->toArray(),
            function ($val) {
                $this->crud->addClause('where', 'group', $val);
                // $this->crud->query = $this->crud->query->where('draft', '1');
            }
        );
    }

    /**
     * @throws JsonException
     */
    public function setupUpdateOperation(): void
    {
        CRUD::addField([
            'name' => 'name',
            'label' => trans('backpack::settings.name'),
            'type' => 'text',
            'attributes' => [
                'disabled' => 'disabled',
            ],
        ]);

        CRUD::addField(json_decode(CRUD::getCurrentEntry()->field, true, 512, JSON_THROW_ON_ERROR));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param $key
     * @return RedirectResponse
     * @throws JsonException
     */
    public function editKey($key): RedirectResponse
    {
        $this->crud->hasAccessOrFail('update');

        $this->data['entry'] = Setting::where('key', $key)->first();

        if (is_null($this->data['entry'])) {
            Alert::error("Xảy ra lỗi hệ thống, không tìm thấy {$key}, vui lòng cài đặt lại.")->flash();
            return back();
        }

        $field = array_merge(json_decode($this->data['entry']['field'], true, 512, JSON_THROW_ON_ERROR), ['label' => '']);
        $this->crud->setOperationSetting('fields', [$field]);

        $this->data['crud'] = $this->crud;
        $this->data['title'] = $this->crud->getTitle() ?? trans('backpack::crud.edit') . ' ' . $this->crud->entity_name;

        return view('settings::key', $this->data);
    }

    /**
     * Update the specified resource in the database.
     *
     * @param $id
     * @return array|RedirectResponse
     */
    public function updatekey($id): array|RedirectResponse
    {
        $this->crud->hasAccessOrFail('update');

        $request = $this->crud->validateRequest();

        $this->crud->registerFieldEvents();

        Setting::where('id', $id)->update([
            'value' => $request->value
        ]);

        Alert::success(trans('backpack::crud.update_success'))->flash();

        return back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param $group
     * @return View
     */
    public function editGroup($group): View
    {
        $this->crud->hasAccessOrFail('update');

        $settings = Setting::where('group', $group)->get();

        $fields = [];
        foreach ($settings as $setting) {
            $fields[] = array_merge(json_decode($setting->field, true), ['label' => $setting->name, 'name' => $setting->key, 'value' => $setting->value]);
        }

        $this->crud->setOperationSetting('fields', $fields);
        $this->data['entry'] = $this->crud->getEntryWithLocale($setting->id);

        $this->data['crud'] = $this->crud;
        $this->data['title'] = $this->crud->getTitle() ?? trans('backpack::crud.edit') . ' ' . $this->crud->entity_name;
        $this->crud->enableTabs();

        return view('settings::group', $this->data);
    }

    /**
     * Update the specified resource in the database.
     *
     * @param $group
     * @return array|RedirectResponse
     */
    public function updateGroup($group): array|RedirectResponse
    {
        $this->crud->hasAccessOrFail('update');

        $request = $this->crud->validateRequest();

        $this->crud->registerFieldEvents();

        $settings = Setting::where('group', $group)->get();

        foreach ($settings as $setting) {
            $setting->update([
                'value' => $request[$setting->key]
            ]);
        }

        Alert::success(trans('backpack::crud.update_success'))->flash();

        return back();
    }
}
