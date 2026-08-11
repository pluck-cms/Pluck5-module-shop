<?php
/**
 * What is for sale.
 *
 * @var array<string,array{name:string,intro:string}> $categories
 * @var list<array<string,mixed>> $products
 * @var int $orders
 * @var array<string,mixed> $money
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Module\Shop\ShopModule;
?>
<h1><?= $view->t('shop.title') ?></h1>

<p>
	<a class="btn" href="<?= e(Controller::url('module.shop.product')) ?>"><?= $view->t('shop.product.new') ?></a>
	<a class="btn-quiet" href="<?= e(Controller::url('module.shop.orders')) ?>">
		<?= $view->t('shop.orders.title') ?> (<?= e((string) $orders) ?>)
	</a>
</p>

<div class="card">
	<h2><?= $view->t('shop.categories') ?></h2>
	<p class="hint"><?= $view->t('shop.categories.help') ?></p>

<?php if ($categories !== []): ?>
	<table>
		<thead>
			<tr>
				<th scope="col"><?= $view->t('shop.category.name') ?></th>
				<th scope="col"><?= $view->t('shop.category.marker') ?></th>
				<th scope="col"><span class="visually-hidden"><?= $view->t('shop.actions') ?></span></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ($categories as $slug => $category): ?>
			<tr>
				<td><?= e($category['name']) ?></td>
				<td><code>[module:shop category=<?= e($slug) ?>]</code></td>
				<td>
					<button class="btn-quiet" type="submit" form="cat-<?= e($slug) ?>"
					        data-confirm="<?= e($view->t('shop.category.confirm', ['name' => $category['name']])) ?>">
						<?= $view->t('shop.action.remove') ?>
					</button>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

	<h3><?= $view->t('shop.category.add') ?></h3>

	<form method="post" action="<?= e(Controller::url('module.shop.category')) ?>">
		<?= $view->csrfField() ?>

		<div class="field">
			<label for="cat-name"><?= $view->t('shop.category.name') ?></label>
			<input id="cat-name" name="name" type="text" maxlength="80" required>
		</div>

		<div class="field">
			<label for="cat-slug"><?= $view->t('shop.category.slug') ?></label>
			<span class="hint"><?= $view->t('shop.category.slug_help') ?></span>
			<input id="cat-slug" name="slug" type="text" maxlength="60">
		</div>

		<div class="field">
			<label for="cat-intro"><?= $view->t('shop.category.intro') ?></label>
			<input id="cat-intro" name="intro" type="text" maxlength="500">
		</div>

		<div class="actions">
			<button type="submit"><?= $view->t('shop.action.save') ?></button>
		</div>
	</form>
</div>

<?php /* The remove forms, after the one above and never inside it: a form in a
         form is not nested HTML — the browser closes the first at the second. */ ?>
<?php foreach ($categories as $slug => $category): ?>
<form id="cat-<?= e($slug) ?>" method="post" action="<?= e(Controller::url('module.shop.category.remove')) ?>" hidden>
	<?= $view->csrfField() ?>
	<input type="hidden" name="slug" value="<?= e($slug) ?>">
</form>
<?php endforeach; ?>

<div class="card">
	<h2><?= $view->t('shop.products') ?></h2>

<?php if ($products === []): ?>
	<p class="muted"><?= $view->t('shop.products.none') ?></p>
<?php else: ?>
	<table>
		<thead>
			<tr>
				<th scope="col"><?= $view->t('shop.product.name') ?></th>
				<th scope="col"><?= $view->t('shop.category.name') ?></th>
				<th scope="col"><?= $view->t('shop.product.steps') ?></th>
				<th scope="col"><span class="visually-hidden"><?= $view->t('shop.actions') ?></span></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ($products as $product): ?>
			<tr>
				<td>
					<a href="<?= e(Controller::url('module.shop.product', ['id' => (string) $product['id']])) ?>">
						<?= e((string) $product['name']) ?>
					</a>
<?php if (($product['active'] ?? true) === false): ?>
					<span class="hint"><?= $view->t('shop.product.off') ?></span>
<?php endif; ?>
				</td>
				<td><?= e($categories[(string) ($product['category'] ?? '')]['name'] ?? '—') ?></td>
				<td>
<?php if ($product['tiers'] === []): ?>
					<span class="hint"><?= $view->t('shop.product.no_price') ?></span>
<?php else: ?>
<?php foreach ($product['tiers'] as $tier): ?>
					<?= e(ShopModule::stepLabel($tier)) ?> <?= e(ShopModule::money((float) $tier['price'], $money)) ?><br>
<?php endforeach; ?>
<?php endif; ?>
				</td>
				<td>
					<button class="btn-quiet" type="submit" form="prod-<?= e((string) $product['id']) ?>"
					        data-confirm="<?= e($view->t('shop.product.confirm', ['name' => (string) $product['name']])) ?>">
						<?= $view->t('shop.action.remove') ?>
					</button>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>

<?php foreach ($products as $product): ?>
<form id="prod-<?= e((string) $product['id']) ?>" method="post" action="<?= e(Controller::url('module.shop.product.remove')) ?>" hidden>
	<?= $view->csrfField() ?>
	<input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
</form>
<?php endforeach; ?>

<details class="card">
	<summary><h2><?= $view->t('shop.money.heading') ?></h2></summary>

	<p class="hint"><?= $view->t('shop.money.help') ?></p>

	<form method="post" action="<?= e(Controller::url('module.shop.money')) ?>">
		<?= $view->csrfField() ?>

		<div class="field">
			<label for="symbol"><?= $view->t('shop.money.symbol') ?></label>
			<input id="symbol" name="symbol" type="text" maxlength="8" value="<?= e($money['symbol']) ?>">
		</div>

		<label class="choice-field">
			<input name="after" type="checkbox" value="1"<?= $money['after'] ? ' checked' : '' ?>>
			<span><?= $view->t('shop.money.after') ?></span>
		</label>

		<div class="field">
			<label for="decimals"><?= $view->t('shop.money.decimals') ?></label>
			<?php /* Nought for the yen, three for the Kuwaiti dinar. Two is not a
			         safe assumption, however many currencies have it. */ ?>
			<span class="hint"><?= $view->t('shop.money.decimals_help') ?></span>
			<select id="decimals" name="decimals">
<?php foreach ([0, 2, 3] as $n): ?>
				<option value="<?= $n ?>"<?= $money['decimals'] === $n ? ' selected' : '' ?>><?= $n ?></option>
<?php endforeach; ?>
			</select>
		</div>

		<div class="field">
			<label for="decimal_point"><?= $view->t('shop.money.decimal_point') ?></label>
			<input id="decimal_point" name="decimal_point" type="text" maxlength="1" value="<?= e($money['decimal_point']) ?>">
		</div>

		<div class="field">
			<label for="thousands"><?= $view->t('shop.money.thousands') ?></label>
			<span class="hint"><?= $view->t('shop.money.thousands_help') ?></span>
			<input id="thousands" name="thousands" type="text" maxlength="1" value="<?= e($money['thousands']) ?>">
		</div>

		<p class="muted">
			<?= $view->t('shop.money.example') ?>
			<strong><?= e(ShopModule::money(1234.5, $money)) ?></strong>
		</p>

		<div class="actions">
			<button type="submit"><?= $view->t('shop.action.save_money') ?></button>
		</div>
	</form>
</details>
