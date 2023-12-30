<?php
namespace Modules\User\Models;

use Modules\Agency\Models\Agency;
use Modules\Agency\Models\AgencyAgent;

class User extends \App\User
{
    public function fillByAttr($attributes , $input)
    {
        if(!empty($attributes)){
            foreach ( $attributes as $item ){
                $this->$item = isset($input[$item]) ? ($input[$item]) : null;
            }
        }
    }

    public function agencies(){
        return $this->belongsToMany(Agency::class, AgencyAgent::class, 'agent_id', 'agencies_id');
    }
}
