<div class="hikashop_mellat_end" id="hikashop_mellat_end">
	<span id="hikashop_mellat_end_message" class="hikashop_mellat_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X',$this->payment_name).'<br/>'. JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED');?> 
	</span>
	<span id="hikashop_mellat_end_spinner" class="hikashop_mellat_end_spinner">
		<img src="<?php echo HIKASHOP_IMAGES.'spinner.gif';?>" />
	</span>
	<br/>
	<?php echo $this->vars['mellat']; ?>
</div>