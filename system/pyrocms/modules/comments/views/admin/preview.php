<p class="width-two-thirds float-left">
	<strong><?php echo lang('comments.posted_label');?>:</strong> <?php echo date('M d, Y', $comment->created_on); ?><br/>		
	<strong><?php echo lang('comments.from_label');?>:</strong> <?php echo $comment->name;?>
</p>

<div class="float-right spacer-right">

	<div class="pyro-button">
		<?php if($comment->is_active): ?>
			<?php echo anchor('admin/comments/unapprove/' . $comment->id, lang('comments.deactivate_label'));?>
		<?php else: ?>
			<?php echo anchor('admin/comments/approve/' . $comment->id, lang('comments.activate_label'));?>
		<?php endif; ?>
	</div>
	
	<div class="pyro-button">
		<?php echo anchor('admin/comments/delete/' . $comment->id, 'Delete'); ?>
	</div>
</div>

<hr class="clear-both" />

<p><?php echo nl2br($comment->comment);?></p>