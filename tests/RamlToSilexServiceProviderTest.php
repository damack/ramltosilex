<?php
namespace Damack\RamlToSilex;

use Damack\RamlToSilex\RamlToSilexServiceProvider;
use Damack\Custom\CustomController;
use Doctrine\DBAL\Schema\Schema;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class RamlToSilexServiceProviderTest extends \PHPUnit_Extensions_Database_TestCase {
    private $connection = null;
    private $app = null;

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection() {
        $config = new \Doctrine\DBAL\Configuration();
        $connectionParams = array(
            'driver' => 'pdo_sqlite',
            'path' => __DIR__ . '/db.sqlite'
        );
        $this->connection = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
        return $this->createDefaultDBConnection($this->connection->getWrappedConnection(), 'api');
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet() {
        return new \PHPUnit_Extensions_Database_DataSet_YamlDataSet(__DIR__."/data.yml");
    }

    /**
     * @before
     */
    public function createApp() {
        $app = new Application();
        $app['debug'] = true;

        $app->register(new \Silex\Provider\ServiceControllerServiceProvider());
        $app->register(new \Silex\Provider\DoctrineServiceProvider(), array(
            'dbs.options' => array(
                'test' => array(
                    'driver' => 'pdo_sqlite',
                    'path' => __DIR__ . '/db.sqlite'
                )
            ),
        ));
        $app->register(new RamlToSilexServiceProvider(), array(
            'ramlToSilex.raml_file' => __DIR__ . '/raml/api.raml',
            'ramlToSilex.config_file' => __DIR__ . '/config.json',
            'ramlToSilex.google-app-id' => 'id',
            'ramlToSilex.google-app-secret' => 'secret',
            'ramlToSilex.google-redirect-uri' => 'http://localhost/',
            'ramlToSilex.redirectUri' => 'http://localhost/login.html',
            'ramlToSilex.apiConsole' => false,
            'ramlToSilex.customController' => function() use ($app) {
                return new CustomController($app);
            }
        ));
        $this->app = $app;
    }

    public function testGetList() {
        $request = Request::create('/users', 'GET');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals('[{"id":"1","name":"Hans Walter Admin","mail":"hans.walter@gmail.com","role":"Admin","active":true},{"id":"2","name":"Hans Walter Describer","mail":"hans.walter@gmail.com","role":"Describer","active":true},{"id":"3","name":"Hans Walter Anonymous","mail":"hans.walter@gmail.com","role":"Anonymous","active":true}]', $response->getContent());
    }
    
    public function testGetListQFilter() {
        $request = Request::create('/users?q=role:Admin', 'GET');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals('[{"id":"1","name":"Hans Walter Admin","mail":"hans.walter@gmail.com","role":"Admin","active":true}]', $response->getContent());
    }

    public function testGetListAnonymous() {
        $request = Request::create('/users', 'GET');
        $request->headers->set('Authorization', 'Bearer anonymous');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testPost() {
        $request = Request::create('/users', 'POST', array(), array(), array(), array('CONTENT_TYPE' => 'application/json'), '{"name":"Test", "mail":"test@test.de", "token":"test", "role":"Admin", "active":true}');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testGet() {
        $request = Request::create('/users/3', 'GET');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals('{"id":"3","name":"Hans Walter Anonymous","mail":"hans.walter@gmail.com","role":"Anonymous","active":true}', $response->getContent());
    }

    public function testPut() {
        $request = Request::create('/users/3', 'PUT', array(), array(), array(), array('CONTENT_TYPE' => 'application/json'), '{"name":"Test", "active": true}');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDelete() {
        $request = Request::create('/users/3', 'DELETE');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testAuthGoogleMe() {
        $request = Request::create('/auth/google/me', 'GET');
        $request->headers->set('Authorization', 'Bearer anonymous');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals('{"id":"3","name":"Hans Walter Anonymous","mail":"hans.walter@gmail.com","role":"Anonymous","active":true}', $response->getContent());
    }

    public function testCustomController() {
        $request = Request::create('/users/3/test', 'PUT');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);
        $this->assertEquals('{"id":"1","name":"Hans Walter Admin","mail":"hans.walter@gmail.com","token":"admin","role":"Admin","active":"1"}', $response->getContent());
    }

    public function testToDoPost() {
        $request = Request::create('/users/1/todos', 'POST', array(), array(), array(), array('CONTENT_TYPE' => 'application/json'), '{"id":"1", "name":"Test"}');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testToDoGetList() {
        $request = Request::create('/users/2/todos', 'GET');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals('[]', $response->getContent());

        $request = Request::create('/users/1/todos', 'GET');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals('[{"id":"1","name":"Test"}]', $response->getContent());
    }
    
    public function testToDoDelete() {
        $request = Request::create('/users/1/todos/1', 'DELETE');
        $request->headers->set('Authorization', 'Bearer admin');
        $request->headers->set('Tenant', 'test');
        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }
}
