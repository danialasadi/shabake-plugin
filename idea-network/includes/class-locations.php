<?php
/**
 * Provinces and cities of Iran: terms, seeding and post sync.
 *
 * The importer stores the province and city of every center as plain meta
 * ("ei_province_name" and "ei_city"). Filtering needs real taxonomies, so this
 * class creates the «استان» and «شهر» terms (all 31 provinces plus their
 * cities) and keeps every center linked to them.
 *
 * @package excel-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Province/city taxonomy data and helpers.
 */
class Idea_Network_Locations {

	/**
	 * Option holding the version of the seeded location data.
	 *
	 * @var string
	 */
	const OPTION_VERSION = 'idea_network_locations_version';

	/**
	 * Option holding the offset of a running background sync.
	 *
	 * @var string
	 */
	const OPTION_PENDING = 'idea_network_locations_pending';

	/**
	 * Admin-post action used by the manual sync button.
	 *
	 * @var string
	 */
	const ACTION_SYNC = 'idea_network_sync_locations';

	/**
	 * Nonce action for the manual sync button.
	 *
	 * @var string
	 */
	const NONCE_SYNC = 'idea_network_sync_locations';

	/**
	 * Term meta on a city term holding its province term ID.
	 *
	 * @var string
	 */
	const PROVINCE_META = 'ei_province_term';

	/**
	 * Number of centers synced per request.
	 *
	 * @var int
	 */
	const SYNC_BATCH = 300;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION_SYNC, array( __CLASS__, 'handle_manual_sync' ) );

		// Meta is written by the meta box on priority 10; link the terms afterwards.
		add_action( 'save_post_' . Idea_Network_CPT::POST_TYPE, array( __CLASS__, 'sync_post_terms' ), 20 );
	}

	/**
	 * Returns the provinces and their cities.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function get_data() {
		return array(
			'آذربایجان شرقی' => array( 'تبریز', 'مراغه', 'مرند', 'اهر', 'میانه', 'بناب', 'سراب', 'آذرشهر', 'اسکو', 'بستان‌آباد', 'شبستر', 'هریس', 'کلیبر', 'هشترود', 'جلفا', 'ملکان', 'عجب‌شیر', 'سهند', 'سردرود', 'خسروشاه', 'باسمنج', 'مبارک‌شهر', 'ترکمانچای', 'هادی‌شهر', 'صوفیان', 'تسوج', 'ایلخچی', 'خامنه', 'شرفخانه', 'یامچی', 'خواجه', 'ورزقان', 'خداآفرین' ),
			'آذربایجان غربی' => array( 'ارومیه', 'خوی', 'میاندوآب', 'مهاباد', 'بوکان', 'سلماس', 'نقده', 'پیرانشهر', 'ماکو', 'سردشت', 'شاهیندژ', 'تکاب', 'شوط', 'چایپاره', 'اشنویه', 'چالدران', 'پلدشت', 'سیه‌چشمه', 'بازرگان', 'ربط', 'کشاورز', 'فیرورق', 'نازک علیا', 'قره‌ضیاءالدین' ),
			'اردبیل' => array( 'اردبیل', 'پارس‌آباد', 'مشگین‌شهر', 'خلخال', 'گرمی', 'بیله‌سوار', 'نمین', 'نیر', 'کوثر', 'اصلاندوز', 'سرعین', 'جعفرآباد', 'لاهرود', 'هشتجین', 'قسابه' ),
			'اصفهان' => array( 'اصفهان', 'کاشان', 'خمینی‌شهر', 'نجف‌آباد', 'شاهین‌شهر', 'شهرضا', 'فولادشهر', 'زرین‌شهر', 'فلاورجان', 'گلپایگان', 'آران و بیدگل', 'دولت‌آباد', 'سمیرم', 'فریدون‌شهر', 'چادگان', 'نطنز', 'نایین', 'اردستان', 'مبارکه', 'دهاقان', 'دهق', 'کوهپایه', 'هرند', 'ورزنه', 'بادرود', 'نوش‌آباد', 'قمصر', 'وزوان', 'زیار', 'سجزی', 'حسن‌آباد', 'مجلسی', 'امیریه' ),
			'البرز' => array( 'کرج', 'فردیس', 'هشتگرد', 'نظرآباد', 'اشتهارد', 'طالقان', 'گرمدره', 'کمال‌شهر', 'محمدشهر', 'مشکین‌دشت', 'چهارباغ', 'کوهسار', 'تنکمان', 'آسارا', 'گلسار' ),
			'ایلام' => array( 'ایلام', 'دهلران', 'آبدانان', 'دره‌شهر', 'مهران', 'ایوان', 'ملکشاهی', 'سرابله', 'آسمان‌آباد', 'بدره', 'مورموری', 'زرین‌آباد', 'پهله', 'صالح‌آباد', 'چوار', 'هلیلان', 'سراب‌باغ', 'ماژین', 'موسیان', 'عین‌خوش' ),
			'بوشهر' => array( 'بوشهر', 'برازجان', 'گناوه', 'دیلم', 'کنگان', 'عسلویه', 'جم', 'دیر', 'اهرم', 'خورموج', 'نخل تقی', 'بندر ریگ', 'آب‌پخش', 'سعدآباد', 'شبانکاره', 'دلوار', 'بیدخون', 'انارستان', 'بردستان', 'امام حسن' ),
			'تهران' => array( 'تهران', 'اسلامشهر', 'شهریار', 'قدس', 'ملارد', 'پاکدشت', 'ورامین', 'قرچک', 'پیشوا', 'رباط‌کریم', 'بهارستان', 'پردیس', 'دماوند', 'فیروزکوه', 'شمیرانات', 'لواسان', 'نسیم‌شهر', 'صباشهر', 'شاهدشهر', 'اندیشه', 'چهاردانگه', 'گلستان', 'صفادشت', 'باغستان', 'نصیرشهر', 'کهریزک', 'حسن‌آباد', 'جوادآباد', 'باقرشهر', 'وحیدیه', 'فردوسیه', 'ارجمند', 'رودهن', 'آبسرد' ),
			'چهارمحال و بختیاری' => array( 'شهرکرد', 'بروجن', 'فارسان', 'لردگان', 'فرخ‌شهر', 'سامان', 'بن', 'اردل', 'ناغان', 'هفشجان', 'طاقانک', 'باباحیدر', 'شلمزار', 'آلونی', 'بلداجی', 'گندمان', 'نقنه', 'سفیددشت', 'چالشتر', 'وردنجان', 'سودجان', 'دستنا', 'مال‌خلیفه' ),
			'خراسان جنوبی' => array( 'بیرجند', 'قائن', 'طبس', 'نهبندان', 'فردوس', 'بشرویه', 'سربیشه', 'خوسف', 'اسدیه', 'سرایان', 'آیسک', 'مود', 'ارسک', 'خضری دشت‌بیاض', 'نیمبلوک', 'شوسف', 'عشق‌آباد' ),
			'خراسان رضوی' => array( 'مشهد', 'نیشابور', 'سبزوار', 'تربت‌حیدریه', 'قوچان', 'کاشمر', 'گناباد', 'چناران', 'درگز', 'سرخس', 'فریمان', 'بردسکن', 'خواف', 'رشتخوار', 'تربت‌جام', 'جغتای', 'جوین', 'خلیل‌آباد', 'داورزن', 'زاوه', 'صالح‌آباد', 'طرقبه شاندیز', 'فیض‌آباد', 'کلات', 'مه‌ولات', 'باخرز', 'بجستان', 'خوشاب', 'جنگل', 'قدمگاه', 'شهرآباد', 'نقاب', 'روداب', 'سنخواست', 'انابد', 'یونسی', 'کدکن', 'شادمهر', 'بایگ' ),
			'خراسان شمالی' => array( 'بجنورد', 'شیروان', 'اسفراین', 'آشخانه', 'گرمه', 'جاجرم', 'فاروج', 'راز', 'مانه', 'صفی‌آباد', 'زیارت', 'حصارگرمخان', 'قاضی', 'لوجلی', 'تیتکانلو', 'درق', 'شوقان' ),
			'خوزستان' => array( 'اهواز', 'آبادان', 'خرمشهر', 'دزفول', 'اندیمشک', 'بهبهان', 'شوشتر', 'مسجدسلیمان', 'ایذه', 'شوش', 'باغملک', 'ماهشهر', 'هندیجان', 'امیدیه', 'رامهرمز', 'رامشیر', 'هفتکل', 'لالی', 'سوسنگرد', 'حمیدیه', 'شادگان', 'هویزه', 'ملاثانی', 'گتوند', 'قلعه تل', 'چمران', 'اروندکنار', 'بندر امام خمینی', 'ترکالکی', 'شیبان' ),
			'زنجان' => array( 'زنجان', 'ابهر', 'خرمدره', 'قیدار', 'صائین‌قلعه', 'هیدج', 'ماهنشان', 'زرین‌آباد', 'آب‌بر', 'گرماب', 'سجاس', 'حلب', 'چورزق', 'دندی', 'ارمغانخانه', 'کرسف', 'سهرورد' ),
			'سمنان' => array( 'سمنان', 'شاهرود', 'گرمسار', 'دامغان', 'مهدی‌شهر', 'بسطام', 'میامی', 'آرادان', 'سرخه', 'مجن', 'بیارجمند', 'شهمیرزاد', 'امیریه', 'ایوانکی', 'رودیان', 'کلاته خیج' ),
			'سیستان و بلوچستان' => array( 'زاهدان', 'زابل', 'چابهار', 'ایرانشهر', 'سراوان', 'خاش', 'کنارک', 'نیک‌شهر', 'سرباز', 'راسک', 'میرجاوه', 'مهرستان', 'سیب و سوران', 'دلگان', 'قصرقند', 'فنوج', 'بمپور', 'محمدان', 'ادیمی', 'نصرت‌آباد', 'دوست‌محمد', 'زهک', 'هیرمند', 'نیمروز', 'هامون', 'گشت', 'پیشین', 'بنت', 'اسپکه', 'لاشار' ),
			'فارس' => array( 'شیراز', 'مرودشت', 'کازرون', 'جهرم', 'فسا', 'لار', 'داراب', 'آباده', 'نی‌ریز', 'صفاشهر', 'اقلید', 'استهبان', 'فیروزآباد', 'قیر و کارزین', 'لامرد', 'مهر', 'خنج', 'گراش', 'ارسنجان', 'بوانات', 'خرامه', 'سروستان', 'زرین‌دشت', 'سپیدان', 'فراشبند', 'کوار', 'نورآباد ممسنی', 'رستم', 'سعادت‌شهر', 'حاجی‌آباد', 'اوز', 'بیرم', 'دهرم', 'دوزه', 'رونیز', 'قادرآباد', 'کامفیروز', 'ایزدخواست', 'داریان', 'خشت', 'کنارتخته', 'بالاده', 'قائمیه', 'نوجین' ),
			'قزوین' => array( 'قزوین', 'تاکستان', 'آبیک', 'بوئین‌زهرا', 'الوند', 'محمدیه', 'اقبالیه', 'محمودآباد نمونه', 'شال', 'اسفرورین', 'خرمدشت', 'ضیاءآباد', 'آوج', 'معلم کلایه', 'رازمیان', 'نرجه', 'خاکعلی', 'کوهین', 'بیدستان', 'شریفیه' ),
			'قم' => array( 'قم', 'کهک', 'دستجرد', 'قنوات', 'جعفریه', 'سلفچگان', 'پردیسان' ),
			'کردستان' => array( 'سنندج', 'سقز', 'مریوان', 'بانه', 'قروه', 'بیجار', 'کامیاران', 'دیواندره', 'دهگلان', 'سروآباد', 'یاسوکند', 'شویشه', 'چناره', 'بابارشانی', 'زرینه', 'صاحب', 'اورامان تخت', 'بوئین سفلی' ),
			'کرمان' => array( 'کرمان', 'سیرجان', 'رفسنجان', 'جیرفت', 'بم', 'بردسیر', 'کهنوج', 'زرند', 'شهربابک', 'بافت', 'رودبار جنوب', 'فهرج', 'ریگان', 'عنبرآباد', 'منوجان', 'نرماشیر', 'رابر', 'کوهبنان', 'راور', 'انار', 'شهداد', 'گلباف', 'قلعه گنج', 'فاریاب', 'ارزوئیه', 'چترود', 'باغین', 'اختیارآباد', 'جوپار', 'محی‌آباد', 'نگار', 'اندوهجرد' ),
			'کرمانشاه' => array( 'کرمانشاه', 'اسلام‌آباد غرب', 'هرسین', 'کنگاور', 'جوانرود', 'پاوه', 'سنقر', 'صحنه', 'سرپل ذهاب', 'قصر شیرین', 'گیلانغرب', 'ثلاث باباجانی', 'روانسر', 'کرند', 'فیروزآباد', 'ماهیدشت', 'باینگان', 'نوسود', 'ازگله', 'حمیل', 'بیستون', 'کوزران', 'سرمست' ),
			'کهگیلویه و بویراحمد' => array( 'یاسوج', 'سی‌سخت', 'دوگنبدان', 'دهدشت', 'چرام', 'لیکک', 'مادوان', 'سوق', 'قلعه رئیسی', 'دیشموک', 'مارگون', 'لنده', 'باشت', 'سرفاریاب', 'پاتاوه', 'چیتاب' ),
			'گلستان' => array( 'گرگان', 'گنبد کاووس', 'بندر ترکمن', 'علی‌آباد کتول', 'آق‌قلا', 'کردکوی', 'بندرگز', 'مینودشت', 'آزادشهر', 'رامیان', 'کلاله', 'مراوه‌تپه', 'گمیشان', 'اینچه‌برون', 'فاضل‌آباد', 'نوده خاندوز', 'دلند', 'سرخنکلاته', 'گالیکش' ),
			'گیلان' => array( 'رشت', 'بندر انزلی', 'لاهیجان', 'آستارا', 'هشتپر', 'فومن', 'صومعه‌سرا', 'آستانه اشرفیه', 'رودسر', 'لنگرود', 'ماسال', 'شفت', 'رضوانشهر', 'سیاهکل', 'منجیل', 'رودبار', 'خمام', 'کوچصفهان', 'لشت نشا', 'سنگر', 'چابکسر', 'کلاچای', 'واجارگاه', 'املش', 'لوشان', 'جیرنده', 'بره‌سر', 'دیلمان', 'پره‌سر', 'اسالم', 'حویق' ),
			'لرستان' => array( 'خرم‌آباد', 'بروجرد', 'دورود', 'الیگودرز', 'کوهدشت', 'نورآباد', 'پلدختر', 'ازنا', 'الشتر', 'سپیددشت', 'سراب دوره', 'ویسیان', 'معمولان', 'چقابل', 'درب گنبد', 'زاغه', 'هفت‌چشمه', 'بیران‌شهر', 'چالانچولان' ),
			'مازندران' => array( 'ساری', 'بابل', 'آمل', 'قائم‌شهر', 'بهشهر', 'چالوس', 'نوشهر', 'رامسر', 'تنکابن', 'نور', 'فریدونکنار', 'بابلسر', 'محمودآباد', 'نکا', 'جویبار', 'پل سفید', 'زیرآب', 'کلاردشت', 'رویان', 'عباس‌آباد', 'گلوگاه', 'شیرود', 'مرزن‌آباد', 'کجور', 'بلده', 'پول', 'کیاسر', 'امیرکلا', 'بهنمیر', 'خلیل‌شهر', 'گتاب', 'کیاکلا', 'شیرگاه', 'سورک', 'چمستان' ),
			'مرکزی' => array( 'اراک', 'ساوه', 'خمین', 'محلات', 'دلیجان', 'شازند', 'آشتیان', 'تفرش', 'فرمهین', 'مأمونیه', 'کمیجان', 'خنداب', 'مهاجران', 'سربند', 'نوبران', 'آستانه', 'هندودر', 'غرق‌آباد', 'پرندک', 'نراق', 'میلاجرد' ),
			'هرمزگان' => array( 'بندرعباس', 'قشم', 'میناب', 'بندر لنگه', 'رودان', 'حاجی‌آباد', 'پارسیان', 'بستک', 'ابوموسی', 'کیش', 'بندرخمیر', 'سیریک', 'بشاگرد', 'جناح', 'فین', 'تخت', 'هشتبندی', 'گوهران', 'زیارتعلی', 'لمزان', 'هرمز', 'سوزا', 'درگهان' ),
			'همدان' => array( 'همدان', 'ملایر', 'نهاوند', 'تویسرکان', 'اسدآباد', 'بهار', 'کبودرآهنگ', 'رزن', 'فامنین', 'لالجین', 'مریانج', 'جورقان', 'قهاوند', 'سامن', 'ازندریان', 'فرسفج', 'برزول', 'فیروزان', 'زنگنه', 'دمق', 'کرفس', 'گل‌تپه', 'شیرین‌سو', 'قروه درجزین' ),
			'یزد' => array( 'یزد', 'میبد', 'اردکان', 'بافق', 'مهریز', 'ابرکوه', 'هرات', 'تفت', 'اشکذر', 'مروست', 'بهاباد', 'ندوشن', 'عقدا', 'احمدآباد', 'زارچ', 'حمیدیا', 'شاهدیه', 'نیر', 'بفروئیه' ),
		);
	}

	/**
	 * Normalises a province or city name typed by a human or an Excel file.
	 *
	 * Converts Arabic letters to Persian ones and removes an extra «استان»
	 * prefix so «استان فارس» and «فارس» resolve to the same term.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function normalize_name( $name ) {
		$name = trim( (string) $name );
		$name = str_replace( array( 'ي', 'ك', 'ۀ', 'ة' ), array( 'ی', 'ک', 'ه', 'ه' ), $name );
		$name = preg_replace( '/\s+/u', ' ', $name );
		$name = preg_replace( '/^استان\s+/u', '', (string) $name );
		$name = preg_replace( '/\s+(استان)$/u', '', (string) $name );

		return trim( (string) $name );
	}

	/**
	 * Returns a term ID from a term_exists() result.
	 *
	 * @param mixed $exists Result of term_exists().
	 * @return int
	 */
	private static function term_id_from_exists( $exists ) {
		if ( is_array( $exists ) && isset( $exists['term_id'] ) ) {
			return (int) $exists['term_id'];
		}

		if ( is_object( $exists ) && isset( $exists->term_id ) ) {
			return (int) $exists->term_id;
		}

		return (int) $exists;
	}

	/**
	 * Returns the «استان» term for a name, creating it when missing.
	 *
	 * @param string $name Province name.
	 * @return WP_Term|null
	 */
	public static function get_or_create_province( $name ) {
		$name = self::normalize_name( $name );

		if ( '' === $name ) {
			return null;
		}

		$existing = term_exists( $name, Idea_Network_CPT::TAXONOMY_PROVINCE );

		if ( ! $existing ) {
			$existing = wp_insert_term( $name, Idea_Network_CPT::TAXONOMY_PROVINCE );
		}

		$term_id = is_wp_error( $existing ) ? 0 : self::term_id_from_exists( $existing );

		if ( ! $term_id ) {
			return null;
		}

		$term = get_term( $term_id, Idea_Network_CPT::TAXONOMY_PROVINCE );

		return ( $term && ! is_wp_error( $term ) ) ? $term : null;
	}

	/**
	 * Returns the «شهر» term for a name inside a province, creating it when missing.
	 *
	 * A city name can exist in several provinces (e.g. «نورآباد»). WordPress does
	 * not allow two terms with the same name in one taxonomy, so the second one is
	 * stored as «نام (استان)» and linked to its province through term meta.
	 *
	 * @param string       $name     City name.
	 * @param WP_Term|null $province Province term.
	 * @return int City term ID (0 on failure).
	 */
	public static function get_or_create_city( $name, $province = null ) {
		$name = self::normalize_name( $name );

		if ( '' === $name ) {
			return 0;
		}

		$province_id = ( $province instanceof WP_Term ) ? (int) $province->term_id : 0;
		$term_id     = 0;

		$existing = term_exists( $name, Idea_Network_CPT::TAXONOMY_CITY );

		if ( $existing ) {
			$existing_id = self::term_id_from_exists( $existing );
			$linked      = (int) get_term_meta( $existing_id, self::PROVINCE_META, true );

			// Same city in the same province (or a city without a province yet).
			if ( ! $linked || ! $province_id || $linked === $province_id ) {
				$term_id = $existing_id;

				if ( $province_id && ! $linked ) {
					update_term_meta( $term_id, self::PROVINCE_META, $province_id );
				}
			}
		}

		if ( ! $term_id ) {
			$term_name = $name;

			// The plain name belongs to another province: append the province name.
			if ( $existing && $province_id ) {
				$province_name = ( $province instanceof WP_Term ) ? $province->name : '';

				if ( '' !== $province_name ) {
					$term_name = $name . ' (' . $province_name . ')';
				}

				$suffixed = term_exists( $term_name, Idea_Network_CPT::TAXONOMY_CITY );

				if ( $suffixed ) {
					$term_id = self::term_id_from_exists( $suffixed );
				}
			}

			if ( ! $term_id ) {
				$created = wp_insert_term( $term_name, Idea_Network_CPT::TAXONOMY_CITY );

				if ( is_wp_error( $created ) ) {
					return 0;
				}

				$term_id = self::term_id_from_exists( $created );
			}

			if ( $province_id && $term_id ) {
				update_term_meta( $term_id, self::PROVINCE_META, $province_id );
			}
		}

		return (int) $term_id;
	}

	/**
	 * Creates every province and city term that is still missing.
	 *
	 * @return array{provinces:int,cities:int}
	 */
	public static function seed() {
		$counts = array(
			'provinces' => 0,
			'cities'    => 0,
		);

		foreach ( self::get_data() as $province_name => $cities ) {
			$province = self::get_or_create_province( $province_name );

			if ( ! $province ) {
				continue;
			}

			++$counts['provinces'];

			foreach ( array_unique( $cities ) as $city_name ) {
				if ( self::get_or_create_city( $city_name, $province ) ) {
					++$counts['cities'];
				}
			}
		}

		update_option( self::OPTION_VERSION, IDEA_NETWORK_VERSION, false );

		return $counts;
	}

	/**
	 * Links a center to its province and city terms based on its meta fields.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function sync_post_terms( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( Idea_Network_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$meta_fields = Idea_Network_CPT::get_meta_fields();
		$province    = '';
		$city        = '';

		foreach ( $meta_fields as $field ) {
			if ( 'ei_province_name' === $field['meta_key'] ) {
				$province = Idea_Network_CPT::get_field_value( $post_id, $field );
			} elseif ( 'ei_city' === $field['meta_key'] ) {
				$city = Idea_Network_CPT::get_field_value( $post_id, $field );
			}
		}

		$province_term = '' !== trim( (string) $province ) ? self::get_or_create_province( $province ) : null;
		$city_term     = 0;

		if ( '' !== trim( (string) $city ) ) {
			$city_term = self::get_or_create_city( $city, $province_term );
		}

		wp_set_object_terms(
			$post_id,
			$province_term ? array( (int) $province_term->term_id ) : array(),
			Idea_Network_CPT::TAXONOMY_PROVINCE,
			false
		);

		wp_set_object_terms(
			$post_id,
			$city_term ? array( $city_term ) : array(),
			Idea_Network_CPT::TAXONOMY_CITY,
			false
		);
	}

	/**
	 * Syncs a batch of existing centers (imported before the taxonomies existed).
	 *
	 * @param int $offset Offset used for pagination.
	 * @return array{synced:int,next_offset:int,done:bool}
	 */
	public static function sync_posts_batch( $offset = 0 ) {
		$offset = max( 0, (int) $offset );

		$ids = get_posts(
			array(
				'post_type'              => Idea_Network_CPT::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => self::SYNC_BATCH,
				'offset'                 => $offset,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		foreach ( $ids as $id ) {
			self::sync_post_terms( (int) $id );
		}

		$synced = count( $ids );

		return array(
			'synced'      => $synced,
			'next_offset' => $offset + $synced,
			'done'        => $synced < self::SYNC_BATCH,
		);
	}

	/**
	 * Seeds the terms once per plugin version and finishes old syncs.
	 *
	 * Runs on admin_init so the front end never pays for it.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$version = get_option( self::OPTION_VERSION );

		if ( IDEA_NETWORK_VERSION !== $version ) {
			self::seed();

			// Link the centers imported before the province/city taxonomies existed.
			update_option( self::OPTION_PENDING, 0, false );
		}

		$pending = get_option( self::OPTION_PENDING );

		if ( false === $pending ) {
			return;
		}

		$batch = self::sync_posts_batch( (int) $pending );

		if ( $batch['done'] ) {
			delete_option( self::OPTION_PENDING );
		} else {
			update_option( self::OPTION_PENDING, (int) $batch['next_offset'], false );
		}
	}

	/**
	 * Registers the «استان و شهر» submenu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Idea_Network_CPT::POST_TYPE,
			__( 'استان و شهر', 'excel-importer' ),
			__( 'استان و شهر', 'excel-importer' ),
			'manage_options',
			'idea-network-locations',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handles the manual sync button.
	 *
	 * @return void
	 */
	public static function handle_manual_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای این عملیات را ندارید.', 'excel-importer' ) );
		}

		check_admin_referer( self::NONCE_SYNC );

		self::seed();
		delete_option( self::OPTION_PENDING );

		$total = 0;
		$batch = self::sync_posts_batch( 0 );

		while ( ! $batch['done'] ) {
			$total += $batch['synced'];
			$batch  = self::sync_posts_batch( $batch['next_offset'] );
		}

		$total += $batch['synced'];

		set_transient(
			'idea_network_locations_synced_' . get_current_user_id(),
			(int) $total,
			15 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . Idea_Network_CPT::POST_TYPE . '&page=idea-network-locations' )
		);
		exit;
	}

	/**
	 * Renders the «استان و شهر» admin page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای این عملیات را ندارید.', 'excel-importer' ) );
		}

		$synced_key = 'idea_network_locations_synced_' . get_current_user_id();
		$synced     = get_transient( $synced_key );

		if ( false !== $synced ) {
			delete_transient( $synced_key );
		}

		$provinces   = get_terms(
			array(
				'taxonomy'   => Idea_Network_CPT::TAXONOMY_PROVINCE,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$provinces   = is_wp_error( $provinces ) ? array() : $provinces;
		$city_counts = array();

		$cities = get_terms(
			array(
				'taxonomy'   => Idea_Network_CPT::TAXONOMY_CITY,
				'hide_empty' => false,
				'fields'     => 'all',
			)
		);
		$cities = is_wp_error( $cities ) ? array() : $cities;

		foreach ( $cities as $city ) {
			$province_id = (int) get_term_meta( $city->term_id, self::PROVINCE_META, true );

			if ( ! isset( $city_counts[ $province_id ] ) ) {
				$city_counts[ $province_id ] = 0;
			}

			++$city_counts[ $province_id ];
		}

		$sync_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_SYNC ),
			self::NONCE_SYNC
		);

		?>
		<div class="wrap ei-wrap">
			<h1><?php esc_html_e( 'استان و شهر', 'excel-importer' ); ?></h1>

			<?php if ( false !== $synced ) : ?>
				<div class="ei-notice is-success">
					<h3>
						<?php
						printf(
							/* translators: %s: number of centers. */
							esc_html__( 'استان و شهر %s مرکز هم‌گام شد.', 'excel-importer' ),
							esc_html( number_format_i18n( (int) $synced ) )
						);
						?>
					</h3>
				</div>
			<?php endif; ?>

			<div class="ei-card">
				<h2><?php esc_html_e( 'فیلتر استان و شهر در المنتور', 'excel-importer' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'هر مرکز بر اساس فیلدهای «نام استان» و «نام شهر» به تاکسونومی‌های استان و شهر وصل می‌شود. برای فیلتر کردن Loop Grid، ویجت «فیلتر حلقه (دسته بندی، استان، شهر)» را از دسته «شبکه ایده پردازی» در المنتور اضافه کنید و «شناسه کوئری» آن را با Query ID ویجت Loop Grid یکی کنید.', 'excel-importer' ); ?>
				</p>

				<?php $widget_file = IDEA_NETWORK_PATH . 'includes/widgets/class-loop-filter-widget.php'; ?>
				<ul class="ei-hints">
					<li>
						<?php
						printf(
							/* translators: %s: Elementor status. */
							esc_html__( 'المنتور: %s', 'excel-importer' ),
							did_action( 'elementor/loaded' )
								? esc_html__( 'فعال است', 'excel-importer' )
								: esc_html__( 'فعال نیست — ویجت فیلتر تا فعال شدن المنتور نمایش داده نمی‌شود', 'excel-importer' )
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: widget registration status. */
							esc_html__( 'ویجت «فیلتر حلقه»: %s', 'excel-importer' ),
							Idea_Network_Elementor::is_widget_registered()
								? esc_html__( 'در المنتور ثبت شده است', 'excel-importer' )
								: esc_html__( 'ثبت نشده است (فایل ویجت را بررسی کنید)', 'excel-importer' )
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: 1: plugin version, 2: widget file status. */
							esc_html__( 'نسخه افزونه: %1$s — فایل ویجت: %2$s', 'excel-importer' ),
							esc_html( IDEA_NETWORK_VERSION ),
							file_exists( $widget_file )
								? esc_html__( 'موجود است', 'excel-importer' )
								: esc_html__( 'موجود نیست! فایل‌ها کامل آپلود نشده‌اند', 'excel-importer' )
						);
						?>
					</li>
				</ul>

				<p>
					<?php
					printf(
						/* translators: 1: number of provinces, 2: number of cities. */
						esc_html__( 'اکنون %1$s استان و %2$s شهر ثبت شده است.', 'excel-importer' ),
						esc_html( number_format_i18n( count( $provinces ) ) ),
						esc_html( number_format_i18n( count( $cities ) ) )
					);
					?>
				</p>

				<p>
					<a class="button button-primary" href="<?php echo esc_url( $sync_url ); ?>">
						<?php esc_html_e( 'هم‌گام‌سازی دوباره استان و شهر همه مراکز', 'excel-importer' ); ?>
					</a>
				</p>

				<table class="ei-columns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'استان', 'excel-importer' ); ?></th>
							<th><?php esc_html_e( 'تعداد شهر', 'excel-importer' ); ?></th>
							<th><?php esc_html_e( 'پیوند', 'excel-importer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $provinces as $province ) : ?>
							<tr>
								<td><?php echo esc_html( $province->name ); ?></td>
								<td><?php echo esc_html( number_format_i18n( isset( $city_counts[ $province->term_id ] ) ? $city_counts[ $province->term_id ] : 0 ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( (string) get_edit_term_link( $province->term_id, Idea_Network_CPT::TAXONOMY_PROVINCE ) ); ?>">
										<?php esc_html_e( 'ویرایش', 'excel-importer' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
