UPDATE `{pre}touch_ad_position` SET `position_style` = '{foreach $ads as $ad}<div class="swiper-slide">{$ad}</div>{/foreach}';
UPDATE `{pre}touch_ad` SET `end_time` = 1629481600;