<?php
/**
 * Elementor widget: Loop Grid filter by category, province and city.
 *
 * @package excel-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Renders the filter form that drives an Elementor Loop Grid.
 */
class Idea_Network_Loop_Filter_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'idea-network-loop-filter';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'فیلتر حلقه (دسته بندی، استان، شهر)', 'excel-importer' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-filter';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int,string>
	 */
	public function get_categories() {
		return array( Idea_Network_Elementor::WIDGET_CATEGORY );
	}

	/**
	 * Search keywords.
	 *
	 * @return array<int,string>
	 */
	public function get_keywords() {
		return array( 'filter', 'loop', 'grid', 'فیلتر', 'استان', 'شهر', 'دسته بندی' );
	}

	/**
	 * Front-end stylesheet handle.
	 *
	 * @return array<int,string>
	 */
	public function get_style_depends() {
		return array( Idea_Network_Elementor::STYLE_HANDLE );
	}

	/**
	 * Front-end script handle.
	 *
	 * @return array<int,string>
	 */
	public function get_script_depends() {
		return array( Idea_Network_Elementor::SCRIPT_HANDLE );
	}

	/**
	 * Registers the widget controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$controls = \Elementor\Controls_Manager::class;

		$this->start_controls_section(
			'section_filters',
			array(
				'label' => __( 'فیلترها', 'excel-importer' ),
			)
		);

		$this->add_control(
			'show_category',
			array(
				'label'        => __( 'فیلتر دسته بندی', 'excel-importer' ),
				'type'         => $controls::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'category_label',
			array(
				'label'     => __( 'عنوان فیلتر دسته بندی', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'دسته بندی', 'excel-importer' ),
				'condition' => array( 'show_category' => 'yes' ),
			)
		);

		$this->add_control(
			'category_all_label',
			array(
				'label'     => __( 'متن گزینه «همه» دسته بندی', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'همه دسته بندی ها', 'excel-importer' ),
				'condition' => array( 'show_category' => 'yes' ),
			)
		);

		$this->add_control(
			'show_province',
			array(
				'label'        => __( 'فیلتر استان', 'excel-importer' ),
				'type'         => $controls::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'province_label',
			array(
				'label'     => __( 'عنوان فیلتر استان', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'استان', 'excel-importer' ),
				'condition' => array( 'show_province' => 'yes' ),
			)
		);

		$this->add_control(
			'province_all_label',
			array(
				'label'     => __( 'متن گزینه «همه» استان', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'همه استان ها', 'excel-importer' ),
				'condition' => array( 'show_province' => 'yes' ),
			)
		);

		$this->add_control(
			'show_city',
			array(
				'label'        => __( 'فیلتر شهر', 'excel-importer' ),
				'type'         => $controls::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'city_label',
			array(
				'label'     => __( 'عنوان فیلتر شهر', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'شهر', 'excel-importer' ),
				'condition' => array( 'show_city' => 'yes' ),
			)
		);

		$this->add_control(
			'city_all_label',
			array(
				'label'     => __( 'متن گزینه «همه» شهر', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'همه شهرها', 'excel-importer' ),
				'condition' => array( 'show_city' => 'yes' ),
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'        => __( 'جستجو', 'excel-importer' ),
				'type'         => $controls::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'search_placeholder',
			array(
				'label'     => __( 'متن راهنمای جستجو', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'جستجوی نام مرکز', 'excel-importer' ),
				'condition' => array( 'show_search' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_settings',
			array(
				'label' => __( 'تنظیمات', 'excel-importer' ),
			)
		);

		$this->add_control(
			'query_id',
			array(
				'label'       => __( 'شناسه کوئری (Query ID)', 'excel-importer' ),
				'type'        => $controls::TEXT,
				'placeholder' => 'centers',
				'description' => __( 'در ویجت Loop Grid، بخش پرس‌وجو → «شناسه کوئری» را باز کنید و همین مقدار را وارد کنید. اگر خالی بماند، فیلتر روی همه حلقه‌های «مراکز» در صفحه اعمال می‌شود.', 'excel-importer' ),
			)
		);

		$this->add_control(
			'auto_submit',
			array(
				'label'        => __( 'اعمال خودکار فیلتر', 'excel-importer' ),
				'type'         => $controls::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( 'با تغییر هر گزینه، صفحه با فیلتر جدید دوباره بارگذاری می‌شود.', 'excel-importer' ),
			)
		);

		$this->add_control(
			'show_submit',
			array(
				'label'        => __( 'نمایش دکمه اعمال فیلتر', 'excel-importer' ),
				'type'         => $controls::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'submit_label',
			array(
				'label'     => __( 'متن دکمه', 'excel-importer' ),
				'type'      => $controls::TEXT,
				'default'   => __( 'اعمال فیلتر', 'excel-importer' ),
				'condition' => array( 'show_submit' => 'yes' ),
			)
		);

		$this->add_control(
			'cities_by_province',
			array(
				'label'        => __( 'فقط شهرهای استان انتخاب‌شده', 'excel-importer' ),
				'type'         => $controls::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( 'وقتی استانی انتخاب شود، فهرست شهرها به شهرهای همان استان محدود می‌شود.', 'excel-importer' ),
				'separator'    => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'استایل', 'excel-importer' ),
				'tab'   => $controls::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'field_typography',
				'selector' => '{{WRAPPER}} .shabake-loop-filter__control',
			)
		);

		$this->add_control(
			'field_color',
			array(
				'label'     => __( 'رنگ متن', 'excel-importer' ),
				'type'      => $controls::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .shabake-loop-filter__control' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'field_background',
			array(
				'label'     => __( 'رنگ پس‌زمینه فیلدها', 'excel-importer' ),
				'type'      => $controls::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .shabake-loop-filter__control' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'field_border',
				'selector' => '{{WRAPPER}} .shabake-loop-filter__control',
			)
		);

		$this->add_control(
			'field_radius',
			array(
				'label'      => __( 'شعاع گوشه‌ها', 'excel-importer' ),
				'type'       => $controls::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .shabake-loop-filter__control' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'focus_border_color',
			array(
				'label'     => __( 'رنگ حاشیه در حالت فعال', 'excel-importer' ),
				'type'      => $controls::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .shabake-loop-filter__control:focus' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'wrapper_background',
			array(
				'label'     => __( 'رنگ پس‌زمینه نوار فیلتر', 'excel-importer' ),
				'type'      => $controls::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .shabake-loop-filter__inner' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'wrapper_gap',
			array(
				'label'     => __( 'فاصله بین فیلدها', 'excel-importer' ),
				'type'      => $controls::SLIDER,
				'range'     => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .shabake-loop-filter__inner' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'wrapper_padding',
			array(
				'label'      => __( 'فاصله داخلی نوار فیلتر', 'excel-importer' ),
				'type'       => $controls::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .shabake-loop-filter__inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Renders the widget on the front end.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$show_category = 'yes' === $this->get_yes_no( $settings, 'show_category' );
		$show_province = 'yes' === $this->get_yes_no( $settings, 'show_province' );
		$show_city     = 'yes' === $this->get_yes_no( $settings, 'show_city' );
		$show_search   = 'yes' === $this->get_yes_no( $settings, 'show_search' );
		$show_submit   = 'yes' === $this->get_yes_no( $settings, 'show_submit' );

		$auto_submit        = 'yes' === $this->get_yes_no( $settings, 'auto_submit' );
		$cities_by_province = 'yes' === $this->get_yes_no( $settings, 'cities_by_province' );
		$query_id           = isset( $settings['query_id'] ) ? Idea_Network_Elementor::normalize_query_id( $settings['query_id'] ) : '';

		if ( '' !== $query_id ) {
			Idea_Network_Elementor::register_query_id( $query_id );
		}

		$params = Idea_Network_Elementor::get_filter_params();

		$labels = array(
			'category'       => isset( $settings['category_label'] ) ? (string) $settings['category_label'] : __( 'دسته بندی', 'excel-importer' ),
			'category_all'   => isset( $settings['category_all_label'] ) ? (string) $settings['category_all_label'] : __( 'همه دسته بندی ها', 'excel-importer' ),
			'province'       => isset( $settings['province_label'] ) ? (string) $settings['province_label'] : __( 'استان', 'excel-importer' ),
			'province_all'   => isset( $settings['province_all_label'] ) ? (string) $settings['province_all_label'] : __( 'همه استان ها', 'excel-importer' ),
			'city'           => isset( $settings['city_label'] ) ? (string) $settings['city_label'] : __( 'شهر', 'excel-importer' ),
			'city_all'       => isset( $settings['city_all_label'] ) ? (string) $settings['city_all_label'] : __( 'همه شهرها', 'excel-importer' ),
			'search'         => isset( $settings['search_placeholder'] ) ? (string) $settings['search_placeholder'] : __( 'جستجوی نام مرکز', 'excel-importer' ),
			'submit'         => isset( $settings['submit_label'] ) ? (string) $settings['submit_label'] : __( 'اعمال فیلتر', 'excel-importer' ),
		);

		$this->add_render_attribute(
			'form',
			array(
				'class'         => 'shabake-loop-filter',
				'method'        => 'get',
				'action'        => Idea_Network_Elementor::get_form_action(),
				'data-settings' => wp_json_encode(
					array(
						'autoSubmit'       => $auto_submit,
						'citiesByProvince' => $cities_by_province,
					)
				),
			)
		);

		$categories = $show_category ? Idea_Network_Elementor::get_categories_terms() : array();
		$provinces  = $show_province || $show_city ? Idea_Network_Elementor::get_provinces() : array();
		$cities     = $show_city ? Idea_Network_Elementor::get_cities() : array();

		$category_parents = array();

		foreach ( $categories as $category_term ) {
			$category_parents[ (int) $category_term->term_id ] = (int) $category_term->parent;
		}

		?>
		<form <?php echo $this->get_render_attribute_string( 'form' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="shabake-loop-filter__inner">
				<?php foreach ( (array) $this->get_preserved_params() as $param_key => $param_value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $param_key ); ?>" value="<?php echo esc_attr( $param_value ); ?>" />
				<?php endforeach; ?>

				<?php if ( $show_search ) : ?>
					<div class="shabake-loop-filter__field shabake-loop-filter__field--search">
						<span class="shabake-loop-filter__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
						</span>
						<input
							type="search"
							class="shabake-loop-filter__control shabake-loop-filter__control--search"
							name="<?php echo esc_attr( Idea_Network_Elementor::PARAM_SEARCH ); ?>"
							value="<?php echo esc_attr( $params['search'] ); ?>"
							placeholder="<?php echo esc_attr( $labels['search'] ); ?>"
							aria-label="<?php echo esc_attr( $labels['search'] ); ?>"
							data-filter="search"
						/>
					</div>
				<?php endif; ?>

				<?php if ( $show_province ) : ?>
					<div class="shabake-loop-filter__field">
						<select
							class="shabake-loop-filter__control"
							name="<?php echo esc_attr( Idea_Network_Elementor::PARAM_PROVINCE ); ?>"
							aria-label="<?php echo esc_attr( $labels['province'] ); ?>"
							data-filter="province"
						>
							<option value=""><?php echo esc_html( $labels['province_all'] ); ?></option>
							<?php foreach ( $provinces as $province_term ) : ?>
								<option value="<?php echo esc_attr( $province_term->slug ); ?>" <?php selected( $params['province'], $province_term->slug ); ?>>
									<?php echo esc_html( $province_term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<?php if ( $show_city ) : ?>
					<div class="shabake-loop-filter__field">
						<select
							class="shabake-loop-filter__control"
							name="<?php echo esc_attr( Idea_Network_Elementor::PARAM_CITY ); ?>"
							aria-label="<?php echo esc_attr( $labels['city'] ); ?>"
							data-filter="city"
							data-all-label="<?php echo esc_attr( $labels['city_all'] ); ?>"
							data-cities="<?php echo esc_attr( wp_json_encode( Idea_Network_Elementor::get_cities_payload() ) ); ?>"
						>
							<option value=""><?php echo esc_html( $labels['city_all'] ); ?></option>
							<?php foreach ( $cities as $city_term ) : ?>
								<option value="<?php echo esc_attr( $city_term->slug ); ?>" <?php selected( $params['city'], $city_term->slug ); ?>>
									<?php echo esc_html( $city_term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<?php if ( $show_category ) : ?>
					<div class="shabake-loop-filter__field">
						<select
							class="shabake-loop-filter__control"
							name="<?php echo esc_attr( Idea_Network_Elementor::PARAM_CATEGORY ); ?>"
							aria-label="<?php echo esc_attr( $labels['category'] ); ?>"
							data-filter="category"
						>
							<option value=""><?php echo esc_html( $labels['category_all'] ); ?></option>
							<?php foreach ( $categories as $category_term ) : ?>
								<?php $category_depth = $this->get_term_depth( (int) $category_term->term_id, $category_parents ); ?>
								<option value="<?php echo esc_attr( $category_term->slug ); ?>" <?php selected( $params['category'], $category_term->slug ); ?>>
									<?php echo esc_html( str_repeat( '— ', $category_depth ) . $category_term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<?php if ( $show_submit ) : ?>
					<button type="submit" class="shabake-loop-filter__submit">
						<?php echo esc_html( $labels['submit'] ); ?>
					</button>
				<?php endif; ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Returns a switcher value with a safe default.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Setting key.
	 * @return string
	 */
	private function get_yes_no( array $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return 'yes';
		}

		return ( '' === $settings[ $key ] ) ? 'no' : (string) $settings[ $key ];
	}

	/**
	 * Returns the current URL parameters that must survive the form submission.
	 *
	 * @return array<string,string>
	 */
	private function get_preserved_params() {
		$reserved = Idea_Network_Elementor::get_reserved_params();
		$preserve = array();

		foreach ( (array) $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = (string) $key;

			if ( in_array( $key, $reserved, true ) || is_array( $value ) ) {
				continue;
			}

			$preserve[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
		}

		return $preserve;
	}

	/**
	 * Returns the nesting depth of a category for the indented dropdown.
	 *
	 * @param int             $term_id Term ID.
	 * @param array<int,int>  $parents Map of term ID to parent term ID.
	 * @return int
	 */
	private function get_term_depth( $term_id, array $parents ) {
		$depth = 0;
		$guard = 0;

		while ( $depth < 10 && isset( $parents[ $term_id ] ) && $parents[ $term_id ] > 0 ) {
			$term_id = (int) $parents[ $term_id ];
			++$depth;
			++$guard;

			if ( $guard > 20 ) {
				break;
			}
		}

		return $depth;
	}
}
