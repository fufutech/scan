<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 */
class StockModel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public function code()
    {
        return $this->hasMany(StockCodeModel::class, 'stock_id', 'id');
    }

    public function admin()
    {
        return $this->hasOne(AdminModel::class, 'uid', 'uid');
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts
        = [
            'create_time' => 'datetime:Y-m-d H:i:s',
        ];

    public $timestamps = false;


}
