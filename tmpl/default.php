<?php
// no direct access
defined('_JEXEC') or die('Restricted access');
// add javascript for price and cart, need even for quantity buttons, so we need it almost anywhere
vmJsApi::jPrice();


$col = 1;
$pwidth = ' width' . floor(100 / $products_per_row);
if ($products_per_row > 1) {
    $float = "floatleft";
} else {
    $float = "center";
}
?>
<div class="pvmproduct vmgroup<?php echo $params->get('moduleclass_sfx') ?>">

    <?php if ($headerText) { ?>
        <div class="vmheader"><?php echo $headerText ?></div>
        <?php
    }
    if ($display_style == "div") {
        ?>
        <div class="vmproduct<?php echo $params->get('moduleclass_sfx'); ?> productdetails">
            <?php foreach ($products as $product) { ?>
                <div class="prow <?php echo $pwidth ?> <?php echo $float ?>">
                    <div class="spacer">
                        <?php
                        if (!empty($product->images[0])) {
                            $image = $product->images[0]->displayMediaThumb('class="featuredProductImage" border="0"', FALSE);
                        } else {
                            $image = '';
                        }
                        echo "<div class='pimageproduct'>";
                        echo JHTML::_('link', JRoute::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . $product->virtuemart_product_id . '&virtuemart_category_id=' . $product->virtuemart_category_id), $image, array('title' => $product->product_name));
                        echo '</div>'; //img
                        echo "<div class='pinfoproduct'>";
                        $url = JRoute::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . $product->virtuemart_product_id . '&virtuemart_category_id=' .
                                        $product->virtuemart_category_id);
                        ?>
                        <div class="pproductname">
                            <a href="<?php echo $url ?>"><?php echo $product->product_name ?></a>      
                        </div>
                        <?php
                        if ($show_price) {
                            echo "<div class='pprice'>";
                            if (!empty($product->prices['salesPrice'])) {
                                echo $currency->createPriceDiv('salesPrice', '', $product->prices, FALSE, FALSE, 1.0, TRUE);
                            }
                            echo $currency->createPriceDiv('basePriceWithTax', '', $product->prices);
                            echo "</div>";
                        }
                        if ($show_addtocart) {
                            echo "<div class='paddcart'>";
                            echo mod_p_vm_product::addtocart($product);
                            echo "</div>";
                        }
                        echo "</div>"; //end infoproduct
                        ?>
                    </div> <!--space-->
                    <?php
                    if ($col == $products_per_row && $products_per_row && $col < $totalProd) {
                        echo "	</div>";//end prow
                        echo "<div style='clear:both;'></div>";
                        $col = 1;
                    } else {
                        echo "</div>";//end prow
                        $col++;
                    }
                }
                ?>
            </div>
            <br style='clear:both;'/>

            <?php
        } else {
            $last = count($products) - 1;
            ?>

            <ul class="vmproduct<?php echo $params->get('moduleclass_sfx'); ?> productdetails">
                <?php foreach ($products as $product) : ?>
                    <li class="prow <?php echo $pwidth ?> <?php echo $float ?>">
                        <?php
                        if (!empty($product->images[0])) {
                            $image = $product->images[0]->displayMediaThumb('class="featuredProductImage" border="0"', FALSE);
                        } else {
                            $image = '';
                        }
                        echo "<div class='pimageproduct'>";
                        echo JHTML::_('link', JRoute::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . $product->virtuemart_product_id . '&virtuemart_category_id=' . $product->virtuemart_category_id), $image, array('title' => $product->product_name));
                        echo '</div>';
                        echo "<div class='pinfoproduct'>";
                        $url = JRoute::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . $product->virtuemart_product_id . '&virtuemart_category_id=' .
                                        $product->virtuemart_category_id);
                        ?>
                        <div class="pproductname">
                            <a href="<?php echo $url ?>"><?php echo $product->product_name ?></a>        
                        </div>
                        <?php
                        // $product->prices is not set when show_prices in config is unchecked
                        if ($show_price and isset($product->prices)) {
                            echo "<div class='pprice'>";
                            echo $currency->createPriceDiv('salesPrice', '', $product->prices, FALSE, FALSE, 1.0, TRUE);
                            echo $currency->createPriceDiv('basePriceWithTax', '', $product->prices);
                            echo '</div>';
                        }
                        if ($show_addtocart) {
                            echo "<div class='paddcart'>";
                            echo mod_p_vm_product::addtocart($product);
                        }echo '</div>';
                        echo "</div>"; //end infoproduct
                        ?>
                    </li>
                    <?php
                    if ($col == $products_per_row && $products_per_row && $last) {
                        echo '<div class="clear"></div>';
                        $col = 1;
                    } else {
                        $col++;
                    }
                    $last--;
                endforeach;
                ?>
            </ul>

            <?php
        }
        if ($footerText) :
            ?>
            <div class="vmfooter<?php echo $params->get('moduleclass_sfx') ?>">
                <?php echo $footerText ?>
            </div>
        <?php endif; ?>
    </div>