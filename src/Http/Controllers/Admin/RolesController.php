<?php

namespace Arbory\Base\Http\Controllers\Admin;

use Arbory\Base\Admin\Form;
use Arbory\Base\Admin\Form\Fields\Text;
use Arbory\Base\Admin\Grid;
use Arbory\Base\Admin\Traits\Crudify;
use Arbory\Base\Auth\Roles\Role;
use Arbory\Base\Admin\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

/**
 * Class RoleController
 * @package App\Http\Controllers
 */
class RolesController extends Controller
{
    use Crudify;

    /**
     * @var string
     */
    protected $resource = Role::class;

    /**
     * @param Model $model
     * @return Form
     */
    protected function form( Model $model )
    {
        $form = $this->module()->form( $model, function ( Form $form )
        {
            /**
             * @var $options Collection
             */
            $options = \Admin::modules()->mapWithKeys( function ( Module $value )
            {
                return [ $value->getControllerClass() => (string) $value ];
            } )->sort();

            $form->addField( new Text( 'name' ) )->rules( 'required' );
            $form->addField( ( new Form\Fields\MultipleSelect( 'permissions' ) )->options( $options ) );
        } );

        $form->addEventListener( 'validate.before', function( Request $request ) use ( $model )
        {
            $resource = $request->input( 'resource' );

            if( array_get( $resource, 'permissions' ) )
            {
                return;
            }

            $request->merge( [ 'resource' => array_merge( $resource, [ 'permissions' => [] ] ) ] );
        } );

        $form->addEventListener( 'create.before', function() use ( $model )
        {
            $model->slug = str_slug( $model->name );
        } );

        return $form;
    }

    /**
     * @return Grid
     */
    public function grid()
    {
        return $this->module()->grid( $this->resource(), function ( Grid $grid )
        {
            $grid->column( 'name' )->sortable();
            $grid->column( 'created_at' )->sortable();
            $grid->column( 'updated_at' );
        } );
    }
}
