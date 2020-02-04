# DVS : Drupal-VIVO Synchronizer

Author : David N. Brett<br/>
V.0.4 - October 2019<br/>
Copyright : David N. Brett / EHESS<br/>
david.brett@ehess.fr<br/>

<h2>What is Drupal-VIVO Synchronizer ?</h2>

<p>Drupal-VIVO Synchronizer allows users to browse a VIVO database and make a selection of datasets to import to a drupal site.</p>

<p>It relies on REDIS to mirror a (very) simplified version of a VIVO database. The REDIS database makes data search very fast and efficient, while preventing from overloading the VIVO server.</p>

<p><strong>Drupal-VIVO Synchronizer is still in early developement.</strong></p>

<h2>What Drupal-VIVO Synchronizer currently does ?</h2>

<p><strong>User interface (web_root/index.php) : </strong></p>

<ul>
    <li>Search for any item belonging to the Vclass defined in config.php ;</li>
    <li>Return all data associated to a specific item (in developement);</li>
</ul>

<p><strong>VIVO Cron (load_vivo.php) : </strong></p>

<ul>
    <li>Connect to VIVO (without athentification) ;</li>
    <li>Get all triplets belonging to a vclass using the ListRDF API ;</li>
    <li>Clean the triplets and prepare a batch of subqueries ;</li>
    <li>Query each item from the Vclass using the Linked open data API ;</li>
    <li>Record simplified data from each Vclass item in a REDIS db ;</li>
    <li>Query each "relatedBy" references from the Vclass items ;</li>
    <li>Record simplified data from each "relatedBy" references item in a REDIS db ;</li>
</ul>

<h2>Dependencies</h2>

<ul>
    <li>PHP 7+ with CURL and PHPREDIS modules enabled.</li>
    <li>Redis server.</li>
</ul>

<h2>How to install</h2>

<ol>
    <li>Edit the config.php file to suit your local configuration.</li>
    <li>Run > php load_vivo.php in your terminal (CLI only).</li>
    <li>Point you virtual host (Apache) to /webroot and access it from your browser.</li>
</ol>

<h2>Notes</h2>

<ul>
    <li>So far, the load_vivo.php script (which should be the object of regular cron) takes approximately 3 minutes to process 5 000 references and consumes 50mb of memory.</li>
</ul>