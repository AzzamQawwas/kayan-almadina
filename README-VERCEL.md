# عرض المشروع على Vercel

هذه نسخة عرض سريعة من تطبيق Laravel. ارفع محتويات هذا المجلد إلى مستودع GitHub
ثم استورده في Vercel.

## إعدادات Vercel المطلوبة

لا تضف ملف `.env` إلى المستودع. أضف هذه المتغيرات من:

**Project Settings → Environment Variables**

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://اسم-المشروع.vercel.app
APP_KEY=...
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=file
QUEUE_CONNECTION=database
```

يجب استخدام قاعدة بيانات MySQL خارجية يمكن لـ Vercel الوصول إليها. لا تعتمد على
قاعدة بيانات XAMPP المحلية، لأن Vercel لا يستطيع الاتصال بـ `127.0.0.1` على جهازك.

## بعد النشر

1. نفّذ migrations على قاعدة البيانات الخارجية من جهاز يحتوي PHP وComposer:

   ```bash
   php artisan migrate --force
   ```

2. افتح `/setup` لإنشاء أول حساب مدير إذا كان المسار متاحًا في المشروع.
3. اختبر `/login` وسير الطلبات.

ملاحظة: نظام الملفات في Vercel مؤقت، لذلك لا تستخدمه لتخزين ملفات الرفع الدائمة.
للعرض السريع استخدم البيانات النصية/الصور الموجودة ضمن الحزمة فقط. للاستخدام
الإنتاجي يجب نقل التخزين إلى خدمة ملفات خارجية.

## Important

The endor directory is intentionally excluded to avoid Vercel's manual upload file limit. The Vercel PHP builder installs Composer dependencies from composer.json during deployment. If deploying by Git, commit this source tree; do not upload files one by one.
