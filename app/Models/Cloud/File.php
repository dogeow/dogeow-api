<?php

namespace App\Models\Cloud;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class File extends Model
{
    use HasFactory;

    protected $table = 'cloud_files';

    protected $fillable = [
        'name',
        'original_name',
        'path',
        'mime_type',
        'extension',
        'size',
        'parent_id',
        'user_id',
        'is_folder',
        'description',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_folder' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(File::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(File::class, 'parent_id');
    }
    
    public function getDownloadUrl()
    {
        return route('cloud.files.download', $this->id);
    }
    
    public function getTypeAttribute()
    {
        if ($this->is_folder) {
            return 'folder';
        }
        
        $extension = strtolower($this->extension);
        
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'])) {
            return 'image';
        }
        
        if (in_array($extension, ['pdf'])) {
            return 'pdf';
        }
        
        if (in_array($extension, ['doc', 'docx', 'txt', 'rtf', 'md'])) {
            return 'document';
        }
        
        if (in_array($extension, ['xls', 'xlsx', 'csv'])) {
            return 'spreadsheet';
        }
        
        if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'archive';
        }
        
        if (in_array($extension, ['mp3', 'wav', 'ogg', 'flac'])) {
            return 'audio';
        }
        
        if (in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'mkv'])) {
            return 'video';
        }
        
        return 'other';
    }
} 