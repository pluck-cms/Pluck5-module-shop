<?php
/**
 * What came in.
 *
 * @var list<array<string,mixed>> $orders
 * @var array<string,mixed> $money
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Module\Shop\ShopModule;
?>
<h1><?= $view->t('shop.orders.title') ?></h1>

<p><a class="btn-quiet" href="<?= e(Controller::url('module.shop.index')) ?>"><?= $view->t('shop.action.back') ?></a></p>

<?php if ($orders === []): ?>
<p class="muted"><?= $view->t('shop.orders.none') ?></p>
<?php else: ?>
<?php foreach ($orders as $order): ?>
<div class="card<?= ($order['handled'] ?? false) ? ' is-handled' : '' ?>">
	<h2><?= e((string) $order['name']) ?> &middot; <?= e(ShopModule::money((float) $order['total'], $money)) ?></h2>

	<p class="muted">
		<?= e(substr((string) ($order['received_at'] ?? ''), 0, 16)) ?> &middot;
		<a href="mailto:<?= e((string) $order['email']) ?>"><?= e((string) $order['email']) ?></a>
<?php if (($order['phone'] ?? '') !== ''): ?>
		&middot; <?= e((string) $order['phone']) ?>
<?php endif; ?>
	</p>

	<table>
		<tbody>
<?php foreach ((array) ($order['lines'] ?? []) as $line): ?>
			<tr>
				<td><?= e((string) $line['count']) ?>&times;</td>
				<td><?= e((string) $line['product']) ?> &mdash; <?= e((string) $line['step']) ?></td>
				<td><?= e(ShopModule::money((float) $line['sum'], $money)) ?></td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>

<?php if (($order['note'] ?? '') !== ''): ?>
	<p><strong><?= $view->t('shop.label.note') ?>:</strong> <?= e((string) $order['note']) ?></p>
<?php endif; ?>

	<form method="post" action="<?= e(Controller::url('module.shop.order.handled')) ?>">
		<?= $view->csrfField() ?>
		<input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">
		<button class="btn-quiet" type="submit">
			<?= ($order['handled'] ?? false) ? $view->t('shop.order.reopen') : $view->t('shop.order.done') ?>
		</button>
	</form>
</div>
<?php endforeach; ?>
<?php endif; ?>
