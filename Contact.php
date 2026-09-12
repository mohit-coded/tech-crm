[1mdiff --git a/app/Models/Contact.php b/app/Models/Contact.php[m
[1mindex 5e3b9da..1849cf0 100644[m
[1m--- a/app/Models/Contact.php[m
[1m+++ b/app/Models/Contact.php[m
[36m@@ -6,6 +6,7 @@[m
 use Illuminate\Database\Eloquent\Factories\HasFactory;[m
 use Illuminate\Database\Eloquent\Model;[m
 use Illuminate\Database\Eloquent\Relations\HasMany;[m
[32m+[m[32muse Illuminate\Database\Eloquent\Relations\HasOne;[m
 [m
 class Contact extends Model[m
 {[m
[36m@@ -28,4 +29,14 @@[m [mpublic function messages(): HasMany[m
     {[m
         return $this->hasMany(Message::class);[m
     }[m
[32m+[m
[32m+[m[32m    /**[m
[32m+[m[32m     * This Contact's single most recent Message, for the conversations[m
[32m+[m[32m     * index preview — lets the controller eager-load it directly[m
[32m+[m[32m     * instead of pulling every message just to read the last one.[m
[32m+[m[32m     */[m
[32m+[m[32m    public function latestMessage(): HasOne[m
[32m+[m[32m    {[m
[32m+[m[32m        return $this->hasOne(Message::class)->latestOfMany();[m
[32m+[m[32m    }[m
 }[m
