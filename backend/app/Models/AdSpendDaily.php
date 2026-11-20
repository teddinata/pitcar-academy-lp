<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSpendDaily extends Model
{
    protected $table = 'ad_spend_daily';

    protected $fillable = ['date', 'campaign_id', 'campaign_name', 'spend', 'impressions', 'link_clicks', 'purchases', 'currency'];

    protected function casts(): array
    {
        // date stays a Y-m-d string, as on PageVisit.
        return ['spend' => 'float', 'impressions' => 'integer', 'link_clicks' => 'integer', 'purchases' => 'integer'];
    }
}
