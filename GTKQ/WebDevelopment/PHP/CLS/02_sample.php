<?php
// Anything outside the PHP tags is passed through untouched.
$name    = "World";
$fruits  = ["apple", "banana", "cherry"];
?>
<h1>Hello, <?php echo $name; ?>!</h1>

<p>Today is <?= date("Y-m-d") ?>.</p>

<ul>
<?php foreach ($fruits as $i => $fruit): ?>
  <li><?= $i + 1 ?>. <?= strtoupper($fruit) ?></li>
<?php endforeach; ?>
</ul>

<?php if (count($fruits) > 2): ?>
<p>That is a lot of fruit.</p>
<?php else: ?>
<p>Just a few.</p>
<?php endif; ?>
