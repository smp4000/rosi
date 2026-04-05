<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * MHD-Kontrolle: Erfasste Mindesthaltbarkeitsdaten pro Station.
 *
 * Der 'kenner' ist ein MD5-Hash aus station_id + tms_no + mhd_date
 * und verhindert doppelte Erfassung desselben Artikels mit gleichem MHD.
 */
class Mhd extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mhds';

    protected $fillable = [
        'station_id',
        'date_recorded',
        'recorded_by',
        'tms_no',
        'ean',
        'article_description',
        'supplier',
        'delivery_date',
        'mhd_date',
        'kenner',
        'goods_disposed',
        'disposed_quantity',
    ];

    protected function casts(): array
    {
        return [
            'date_recorded' => 'date',
            'delivery_date' => 'date',
            'mhd_date' => 'date',
            'goods_disposed' => 'boolean',
        ];
    }

    // -- Relationships --

    public function station()
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    // -- Scopes --

    /** Nur Eintraege einer bestimmten Station */
    public function scopeForStation($query, string $stationId)
    {
        return $query->where('station_id', $stationId);
    }

    /** Nur nicht-abgeschriebene Eintraege */
    public function scopeNotDisposed($query)
    {
        return $query->where('goods_disposed', false);
    }

    /** Sortiert nach MHD-Datum aufsteigend (aelteste zuerst) */
    public function scopeOldestFirst($query)
    {
        return $query->orderBy('mhd_date', 'asc');
    }

    // -- Helpers --

    /**
     * Kenner generieren: MD5 aus station_id + tms_no + mhd_date.
     */
    public static function generateKenner(string $stationId, int $tmsNo, string $mhdDate): string
    {
        return md5($stationId . $tmsNo . $mhdDate);
    }

    /**
     * Restlaufzeit in Tagen berechnen (positiv = noch gut, negativ = abgelaufen).
     */
    public function getRemainingDaysAttribute(): int
    {
        return (int) Carbon::today()->diffInDays($this->mhd_date, false);
    }
}
