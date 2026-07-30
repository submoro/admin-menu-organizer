<?php
/**
 * Arabic translation map.
 *
 * A starter translation, as SPEC section 9 asks for, covering the strings a user
 * actually sees while using the plugin. It exists as much to prove the RTL
 * rendering works against real Arabic text as to serve Arabic speakers: SPEC
 * section 6.4 makes RTL a hard requirement because the target site runs WPML
 * with Arabic, and testing that against Latin text proves nothing.
 *
 * Longer explanatory paragraphs are left for a native reviewer rather than
 * guessed at. Any string absent from this map falls back to English, which is
 * correct behaviour and clearly visible.
 *
 * Not shipped in the zip; this is the source for bin/build-translation.php.
 */

return array(

	// Plugin and screen names.
	'Menu Organizer'                => 'منظّم القوائم',

	// Category labels.
	'Dashboard'                     => 'لوحة التحكم',
	'Content'                       => 'المحتوى',
	'Commerce'                      => 'التجارة',
	'Design'                        => 'التصميم',
	'Marketing'                     => 'التسويق',
	'Security'                      => 'الأمان',
	'Performance'                   => 'الأداء',
	'Users'                         => 'المستخدمون',
	'Integrations'                  => 'التكاملات',
	'Tools'                         => 'الأدوات',
	'Other'                         => 'أخرى',

	// Tabs.
	'Layout'                        => 'التنسيق',
	'Groups'                        => 'المجموعات',
	'Roles'                         => 'الأدوار',
	'Advanced'                       => 'إعدادات متقدمة',
	'Personalise my menu'           => 'تخصيص قائمتي',
	'Menu Organizer sections'       => 'أقسام منظّم القوائم',

	// Buttons and actions.
	'Settings'                      => 'الإعدادات',
	'Save'                          => 'حفظ',
	'Save layout'                   => 'حفظ التنسيق',
	'Save groups'                   => 'حفظ المجموعات',
	'Save roles'                    => 'حفظ الأدوار',
	'Import layout'                 => 'استيراد التنسيق',
	'Reset to site default'         => 'إعادة التعيين إلى الافتراضي للموقع',
	'Reset to detected default'     => 'إعادة التعيين إلى الافتراضي المكتشف',
	'Reset'                         => 'إعادة تعيين',
	'Export'                        => 'تصدير',
	'Import'                        => 'استيراد',

	// Table headings and field labels.
	'Order'                         => 'الترتيب',
	'Name'                          => 'الاسم',
	'Icon'                          => 'الأيقونة',
	'Starts open'                   => 'يبدأ مفتوحًا',
	'Items'                         => 'العناصر',
	'Role'                          => 'الدور',
	'Menu layout'                   => 'تنسيق القائمة',
	'Menu item'                     => 'عنصر القائمة',
	'Slug'                          => 'المُعرّف',
	'Detected group'                => 'المجموعة المكتشفة',
	'Decided by'                    => 'حُدّدت بواسطة',
	'Matched on'                    => 'تمّت المطابقة على',
	'Group name'                    => 'اسم المجموعة',
	'Group icon'                    => 'أيقونة المجموعة',
	'Always open'                   => 'مفتوح دائمًا',
	'always visible'                => 'مرئي دائمًا',
	'Personal layouts'              => 'التنسيقات الشخصية',

	// Icon names.
	'Menu'                          => 'قائمة',
	'Post'                          => 'مقالة',
	'Page'                          => 'صفحة',
	'Media'                         => 'وسائط',
	'Cart'                          => 'سلة',
	'Products'                      => 'منتجات',
	'Appearance'                    => 'المظهر',
	'Chart'                         => 'مخطط',
	'Megaphone'                     => 'مكبر صوت',
	'Shield'                        => 'درع',
	'Backup'                        => 'نسخ احتياطي',
	'Links'                         => 'روابط',
	'Tools'                         => 'أدوات',
	'Translation'                   => 'ترجمة',
	'Email'                         => 'بريد إلكتروني',
	'Book'                          => 'كتاب',

	// Role options.
	'Use the site-wide layout'      => 'استخدام تنسيق الموقع العام',
	'Give this role its own copy'   => 'إعطاء هذا الدور نسخته الخاصة',
	'Let each user arrange their own sidebar' => 'السماح لكل مستخدم بترتيب شريطه الجانبي',

	// Notices.
	'Your changes have been saved.' => 'تم حفظ التغييرات.',
	'The layout was imported.'      => 'تم استيراد التنسيق.',

	// Accordion, from JavaScript.
	'expanded'                      => 'موسّع',
	'collapsed'                     => 'مطوي',
	'Moved to group'                => 'تم النقل إلى المجموعة',

	// Headings.
	'If something goes wrong'       => 'إذا حدث خطأ ما',
	'Why items were grouped this way' => 'سبب تجميع العناصر بهذه الطريقة',
	'Layout as JSON'                => 'التنسيق بصيغة JSON',
	'Layout JSON to import'         => 'ملف JSON للتنسيق المراد استيراده',

	// Errors.
	'You are not allowed to access this page.' => 'غير مسموح لك بالوصول إلى هذه الصفحة.',
	'You are not allowed to change these settings.' => 'غير مسموح لك بتغيير هذه الإعدادات.',
	'You are not allowed to personalise your menu.' => 'غير مسموح لك بتخصيص قائمتك.',
	'You must be signed in to save your menu state.' => 'يجب تسجيل الدخول لحفظ حالة قائمتك.',
	'Empty. This group will not appear in the sidebar.' => 'فارغة. لن تظهر هذه المجموعة في الشريط الجانبي.',
);
