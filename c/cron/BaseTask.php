<?php

include_once __DIR__ . '/../v4/modules/redis.trait.php';

abstract class BaseTask {

    use RedisAware;

    const MYSQL = 'mysql';
    const MYSQL_SLAVE = 'mysql_slave';

    protected $db;
    protected $dbSlave;

    private $name;
    private $lockTtl = 10 * 60;
    private $httpClient;

    /**
     * @param string $name
     */
    public function __construct($name) {
        $this->name = $name;
        $this->db = $this->db_connect(self::MYSQL);
        $this->dbSlave = $this->db_connect(self::MYSQL_SLAVE);
    }

    /**
     * @param mixed[] $args
     */
    public function run($args) {
        $key = $this->normalizedKey();
        if ($this->obtainLock($key) === false) {
            return;
        }

        try {
            return $this->action($args);
        } catch (Throwable $exception) {
            $this->log("Worker failed due to error: {$exception->getMessage()}");
        } finally {
            if ($this->release($key) === false) {
                throw new \Exception(`Unable to release lock for '${key}'`);
            }
        }
    }

    /**
     * @param mixed[] $args
     */
    abstract protected function action($args);

    protected function resultSlave($query) {
        $response = $this->querySlave($query);
        $results = [];
        while ($row = $response->fetch_assoc()) {
            $results[] = $row;
        }
        $response->free();
        return $results;
    }

    protected function result($query) {
        $response = $this->query($query);
        $results = [];
        while ($row = $response->fetch_assoc()) {
            $results[] = $row;
        }
        $response->free();
        return $results;
    }

    protected function querySlave($query) {
        $response = $this->dbSlave->query($query);

        if (!$this->dbSlave->error && $response) {
            return $response;
        }

        throw new \Exception("Failed to execute query '$query'");
    }

    protected function query($query) {
        $response = $this->db->query($query);

        if (!$this->db->error && $response) {
            return $response;
        }

        $this->log("Failed to execute query: " . $this->db->error);
        throw new \Exception("Failed to execute query '$query', " . $this->db->error, 0);
    }

    protected function log($message) {
        echo sprintf("[%s] %s: %s" . PHP_EOL, (new \DateTime)->format(DATE_ATOM), $this->name, $message);
    }

    protected function getIni() {
        require_once __DIR__ . '/../core/config.php';
        return getConfig();
    }

    protected function getHttpClient(): \GuzzleHttp\Client {
        if ($this->httpClient === null) {
            $this->httpClient = new \GuzzleHttp\Client(['connect_timeout' => 10]);
        }

        return $this->httpClient;
    }

    protected function doWithinTransaction(callable $callable)
    {
        $this->db->begin_transaction();

        try {
            $return = call_user_func($callable);

            $this->db->commit();

            return $return ?: true;
        } catch (Throwable $e) {
            $this->db->rollback();

            throw $e;
        }
    }

    private function db_connect($configName) {
        $ini = $this->getIni();
        $connection = new mysqli($ini[$configName]['host'], $ini[$configName]['user'], $ini[$configName]['password'], 'anytime', $ini[$configName]['port']);
        $connection->set_charset('utf8');

        return $connection;
    }

    private function normalizedKey() {
        return "task-" . $this->name . ".lock";
    }

    private function obtainLock($key) {
        if (!$this->redis()->exists($key)) {
            $value = sprintf("Host: %s - pid: %s", gethostname(), getmypid());
            return $this->redis()->setex($key, $this->lockTtl, $value);
        }

        $value = $this->redis()->get($key);
        echo sprintf("Another instance of the script is already running: %s", $value);
        return false;
    }

    private function release($key) {
        return $this->redis()->del([$key]);
    }
}
