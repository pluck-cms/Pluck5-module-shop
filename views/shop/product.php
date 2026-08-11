<?php
/**
 * One product, with its price steps.
 *
 * @var string $id
 * @var array<string,array{name:string,intro:string}> $categories
 * @var list<string> $media
 * @var array<string,mixed> $product
 * @var list<array{qty:int,price:float,label:string}> $tiers
 * @var int $maxTiers
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<h1><?= e($title) ?></h1>

<form class="card" method="post" action="<?= e(Controller::url('module.shop.product.save')) ?>">
	<?= $view->csrfField() ?>
	<input type="hidden" name="id" value="<?= e($id) ?>">

	<div class="field">
		<label for="name"><?= $view->t('shop.product.name') ?></label>
		<input id="name" name="name" type="text" maxlength="120" required value="<?= e((string) $product['name']) ?>">
	</div>

	<div class="field">
		<label for="category"><?= $view->t('shop.category.name') ?></label>
		<select id="category" name="category">
<?php foreach ($categories as $slug => $category): ?>
			<option value="<?= e($slug) ?>"<?= $slug === (string) $product['category'] ? ' selected' : '' ?>>
				<?= e($category['name']) ?>
			</option>
<?php endforeach; ?>
		</select>
	</div>

	<div class="field">
		<label for="description"><?= $view->t('shop.product.description') ?></label>
		<span class="hint"><?= $view->t('shop.product.description_help') ?></span>
		<textarea id="description" name="description" rows="4" maxlength="1000"><?= e((string) $product['description']) ?></textarea>
	</div>

	<div class="field">
		<label for="photo"><?= $view->t('shop.product.photo') ?></label>
		<span class="hint"><?= $view->t('shop.product.photo_help') ?></span>
		<select id="photo" name="photo">
			<option value=""><?= $view->t('shop.product.no_photo') ?></option>
<?php foreach ($media as $name): ?>
			<option value="<?= e($name) ?>"<?= $name === (string) $product['photo'] ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
		</select>
	</div>

	<fieldset class="field">
		<legend><?= $view->t('shop.product.steps') ?></legend>
		<span class="hint"><?= $view->t('shop.product.steps_help') ?></span>

		<table class="steps">
			<thead>
				<tr>
					<th scope="col"><?= $view->t('shop.step.qty') ?></th>
					<th scope="col"><?= $view->t('shop.step.label') ?></th>
					<th scope="col"><?= $view->t('shop.step.price') ?></th>
				</tr>
			</thead>
			<tbody>
<?php for ($i = 0; $i < $maxTiers; $i++): ?>
<?php $tier = $tiers[$i] ?? ['qty' => '', 'label' => '', 'price' => '']; ?>
				<tr>
					<td>
						<label class="visually-hidden" for="tier-qty-<?= $i ?>"><?= $view->t('shop.step.qty') ?></label>
						<input id="tier-qty-<?= $i ?>" name="tier_qty_<?= $i ?>" type="number" min="1" max="9999"
						       value="<?= e((string) $tier['qty']) ?>">
					</td>
					<td>
						<label class="visually-hidden" for="tier-label-<?= $i ?>"><?= $view->t('shop.step.label') ?></label>
						<input id="tier-label-<?= $i ?>" name="tier_label_<?= $i ?>" type="text" maxlength="60"
						       value="<?= e((string) $tier['label']) ?>">
					</td>
					<td>
						<label class="visually-hidden" for="tier-price-<?= $i ?>"><?= $view->t('shop.step.price') ?></label>
						<input id="tier-price-<?= $i ?>" name="tier_price_<?= $i ?>" type="text" inputmode="decimal"
						       value="<?= e($tier['price'] === '' ? '' : number_format((float) $tier['price'], 2, ',', '')) ?>">
					</td>
				</tr>
<?php endfor; ?>
			</tbody>
		</table>
	</fieldset>

	<div class="field">
		<label for="order"><?= $view->t('shop.product.order') ?></label>
		<input id="order" name="order" type="number" min="0" max="999" value="<?= e((string) $product['order']) ?>">
	</div>

	<label class="choice-field">
		<input name="active" type="checkbox" value="1"<?= ($product['active'] ?? true) ? ' checked' : '' ?>>
		<span><?= $view->t('shop.product.active') ?></span>
	</label>

	<div class="actions">
		<button type="submit"><?= $view->t('shop.action.save') ?></button>
		<a class="btn-quiet" href="<?= e(Controller::url('module.shop.index')) ?>"><?= $view->t('shop.action.back') ?></a>
	</div>
</form>
