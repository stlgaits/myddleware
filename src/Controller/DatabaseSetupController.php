<?php

namespace App\Controller;

use Exception;
use App\Entity\Config;
use App\Form\DatabaseSetupType;
use App\Entity\DatabaseParameter;
use App\Repository\ConfigRepository;
use Doctrine\DBAL\ConnectionException as DBALConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\DBAL\Driver\PDO\Exception as DBALDriverPDOException;
use Doctrine\DBAL\Exception\TableNotFoundException;

class DatabaseSetupController extends AbstractController
{

    private $connectionSuccessMessage;
    private $connectionFailedMessage;
    private $configRepository;

    private $entityManager;

    public function __construct(ConfigRepository $configRepository, EntityManagerInterface $entityManager)
    {
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("install/database/setup", name="database_setup")
     */
    public function index(Request $request, KernelInterface $kernel): Response
    {

        try {

            $submitted = false;

            //to help voter decide whether we allow access to install process again or not
            $configs = $this->configRepository->findAll();
            if(!empty($configs)){
                foreach($configs as $config) {
                    $this->denyAccessUnlessGranted('DATABASE_EDIT', $config);
                }
            } else {

                //clear database of all previous configs (to prevent conflicts with Voter on whether or not installation is allowed)
                $this->configRepository->deleteAll();
                $config = new Config();
                // will be used by the InstallVoter to determine access to all install routes
                $config->setAllowInstall(true);
                $this->entityManager->persist($config);
                $this->entityManager->flush();
            
            }

            //get all parameters from config/parameters.yml and push them in a new instance of DatabaseParameters()
            $database = new DatabaseParameter();
            $database->setDriver($this->getParameter('database_driver'));
            $database->setHost($this->getParameter('database_host'));
            $database->setPort($this->getParameter('database_port'));
            $database->setName($this->getParameter('database_name'));
            $database->setUser($this->getParameter('database_user'));
            $database->setSecret($this->getParameter('secret'));

            // force user to change the default Symfony secret for security
            if($database->getSecret() === 'ThisTokenIsNotSoSecretChangeIt') {
                $database->setSecret(md5(rand(0,10000).date('YmdHis').'myddleware'));
            }

            $form = $this->createForm(DatabaseSetupType::class, $database);
            $form->handleRequest($request);
    
            // send database parameters to .env.local
            if ($form->isSubmitted() && $form->isValid()){

                $envLocal = __DIR__.'/../../.env.local';

                if(file_exists($envLocal) && is_file($envLocal)){
                    // we edit the database connection parameters with form input
                    $newUrl = 'DATABASE_URL="mysql://'.$database->getUser().':'.$database->getPassword().'@'.$database->getHost().':'.$database->getPort().'/'.$database->getName().'?serverVersion=5.7"';
                    // write new URL into the .env.local file (EOL ensures it's written on a new line)
                    file_put_contents($envLocal, PHP_EOL.$newUrl, LOCK_EX);
                }

                    // allow to proceed to next step
                    $submitted = true;

                }

                return $this->render('database_setup/index.html.twig', [
                    'form' => $form->createView(),
                    'submitted' => $submitted
                ]);

        } catch  (Exception $e) {

            if($e instanceof ConnectionException | $e instanceof TableNotFoundException){

                $submitted = false;
              
                //get all parameters from config/parameters.yml and push them in a new instance of DatabaseParameters()
                $database = new DatabaseParameter();
                $database->setDriver($this->getParameter('database_driver'));
                $database->setHost($this->getParameter('database_host'));
                $database->setPort($this->getParameter('database_port'));
                $database->setName($this->getParameter('database_name'));
                $database->setUser($this->getParameter('database_user'));
                $database->setSecret($this->getParameter('secret'));

                // force user to change the default Symfony secret for security
                if($database->getSecret() === 'ThisTokenIsNotSoSecretChangeIt') {
                    $database->setSecret(md5(rand(0,10000).date('YmdHis').'myddleware'));
                }

                $form = $this->createForm(DatabaseSetupType::class, $database);
                $form->handleRequest($request);
        
                // send database parameters to .env.local
                if ($form->isSubmitted() && $form->isValid()){

                    $envLocal = __DIR__.'/../../.env.local';

                    if(file_exists($envLocal) && is_file($envLocal)){
                        // we edit the database connection parameters with form input
                        $newUrl = 'DATABASE_URL="mysql://'.$database->getUser().':'.$database->getPassword().'@'.$database->getHost().':'.$database->getPort().'/'.$database->getName().'?serverVersion=5.7"';
                        // write new URL into the .env.local file (EOL ensures it's written on a new line)
                        file_put_contents($envLocal, PHP_EOL.$newUrl, LOCK_EX);
                    }

                        // allow to proceed to next step
                        $submitted = true;
                    }

                //if there's already a database in .env.local but it isn't yet linked to database, then allow access to form
                return $this->render('database_setup/index.html.twig', [
                    'form' => $form->createView(),
                    'submitted' => $submitted,
    
                ]);

            } else {
                throw new Exception($e->getMessage().' '. $e->getFile().' Line :  '.$e->getLine());
            }
        }

        //if there's already a database in .env.local but it isn't yet linked to database, then allow access to form
        return $this->render('database_setup/index.html.twig', [
            'form' => $form->createView(),
            'submitted' => $submitted,

        ]);
       
    }

    /**
     * Attempt to connect to database 
     * @Route("install/database/connect", name="database_connect")
     */
    public function connectDatabase(Request $request, KernelInterface $kernel): Response
    {
        try {

            $connected = $this->getDoctrine()->getConnection()->isConnected();

            if($connected){

                //to help voter decide whether we allow access to install process again or not
                $configs = $this->configRepository->findAll();
                if(!empty($configs)){
                    foreach($configs as $config) {
                        $this->denyAccessUnlessGranted('DATABASE_EDIT', $config);
                    }
                } 
            
            } else {     
           
                 //to help voter decide whether we allow access to install process again or not
                 $configs = $this->configRepository->findAll();
                 if(!empty($configs)){
                     foreach($configs as $config) {
                         $this->denyAccessUnlessGranted('DATABASE_EDIT', $config);
                     }
                 } 
             
                $env = $kernel->getEnvironment();

                $application = new Application($kernel);
                $application->setAutoExit(false);
            
                // we execute Doctrine console commands to test the connection to the database
                $input = new ArrayInput(array(
                    'command' => 'doctrine:schema:update',
                    '--force' => true,
                    '--env' => $env
                ));
                $output = new BufferedOutput();
                $application->run($input, $output);
                $content = $output->fetch();

                //send the message sent by Doctrine to the user's view
                $this->connectionSuccessMessage = $content;

                //slight bug : sometimes the ERROR message is sent as a success, so if it's too long we reset it as an error
                if(strlen($this->connectionSuccessMessage) > 150) {
                    $this->connectionFailedMessage = $this->connectionSuccessMessage;
                    // trim the message to remove unnecessary stack trace
                    $this->connectionFailedMessage = strstr($this->connectionFailedMessage, 'Exception trace', true);
                    $this->connectionSuccessMessage = '';
                }
         
                //clear database of all previous configs (to prevent conflicts with Voter on whether or not installation is allowed)
                $this->configRepository->deleteAll();
             
                $config = new Config();
                // will be used by the InstallVoter to determine access to all install routes
                $config->setAllowInstall(true);

            
                $this->entityManager->persist($config);
                $this->entityManager->flush();
            
                return $this->render('database_setup/database_connection.html.twig', [
                    'connection_success_message' =>  $this->connectionSuccessMessage,
                    'connection_failed_message' => $this->connectionFailedMessage,
                ]);
            }  

        } catch(ConnectionException  | DBALException  | Exception $e){
            
            // if the database doesn't exist yet, create it
            if($e instanceof ConnectionException ){
                $this->connectionFailedMessage = 'Unknown database. Please make sure your database exists. <br/> '.$e->getMessage();
                return $this->render('database_setup/database_connection.html.twig', [
                    'connection_success_message' =>  $this->connectionSuccessMessage,
                    'connection_failed_message' => $this->connectionFailedMessage,
                ]);
            }

            return $this->redirectToRoute('database_setup');
        } 
        
        
      }

    /**
     * Attempt to load Myddleware fixtures to database
     * @Route("install/database/fixtures/load", name="database_fixtures_load")
     */
      public function doctrineFixturesLoad(Request $request, KernelInterface $kernel): Response 
      {
      
          //to help voter decide whether we allow access to install process again or not
          $configs = $this->configRepository->findAll();
          if(!empty($configs)){
              foreach($configs as $config) {
                  $this->denyAccessUnlessGranted('DATABASE_EDIT', $config);
              }
          } 

        $env = $kernel->getEnvironment();
        $application = new Application($kernel);
        $application->setAutoExit(false);
    
         //load database tables
         $fixturesInput = new ArrayInput(array(
            'command' => 'doctrine:fixtures:load',
             '--append' => true,
              '--env' => $env
        ));

        $fixturesOutput = new BufferedOutput();
        $application->run($fixturesInput, $fixturesOutput);
        $content = $fixturesOutput->fetch();

 //TODO : bugfix -> this currently doesn't work from here (but it does in terminal command line..)
 // view https://github.com/Myddleware/myddleware/issues/451 to get full error message from doctrine

           //send the message sent by Doctrine to the user's view
           $this->connectionSuccessMessage = $content;
        
           //slight bug : sometimes the ERROR message is sent as a success, so if it's too long we reset it as an error
           if(strlen($this->connectionSuccessMessage) > 150) {
               $this->connectionFailedMessage = $this->connectionSuccessMessage;
               // trim the message to remove unnecessary stack trace
               $this->connectionFailedMessage = strstr($this->connectionFailedMessage, 'Exception trace', true);
               $this->connectionSuccessMessage = '';
           }

        return $this->render('database_setup/load_fixtures.html.twig', 
            [
                'connection_success_message' =>  $this->connectionSuccessMessage,
                'connection_failed_message' => $this->connectionFailedMessage,
            ]);
      }


    

}
