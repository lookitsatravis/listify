<?= '<?php' . PHP_EOL ?>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class <?= $className ?> extends Migration {

    /**
     * Make changes to the table.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('<?= $tableName ?>', function(Blueprint $table) {

            $table->integer("<?= $columnName ?>")->nullable();

        });

    }

    /**
     * Revert the changes to the table.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('<?= $tableName ?>', function(Blueprint $table) {

            $table->dropColumn("<?= $columnName ?>");

        });
    }

}