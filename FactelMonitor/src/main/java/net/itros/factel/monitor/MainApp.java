package net.itros.factel.monitor;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.Banner;
import org.springframework.boot.CommandLineRunner;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

import java.nio.file.*;

import static java.lang.System.exit;

@SpringBootApplication
public class MainApp implements CommandLineRunner {

    @Autowired
    private HelloMessageService helloService;

    @Value("${directorio:unknown}")
    private String directorio;

    public static void main(String[] args) throws Exception {

        //disabled banner, don't want to see the spring logo
        SpringApplication app = new SpringApplication(MainApp.class);
        app.setBannerMode(Banner.Mode.OFF);
        app.run(args);

    }

    // Put your logic here.
    @Override
    public void run(String... args) throws Exception {

        System.out.println("Monitoreando: " + this.directorio);

        WatchService watchService = FileSystems.getDefault().newWatchService();

        Path path = Paths.get(this.directorio);

        try {
            path.register(
                    watchService,
                    StandardWatchEventKinds.ENTRY_CREATE,
                    StandardWatchEventKinds.ENTRY_DELETE,
                    StandardWatchEventKinds.ENTRY_MODIFY);
        } catch (Exception e) {
            System.out.println("El directorio no es válido. ["+this.directorio+"]");
            e.printStackTrace();
            exit(1);
        }

        WatchKey key;
        while ((key = watchService.take()) != null) {
            for (WatchEvent<?> event : key.pollEvents()) {
                System.out.println(
                        "Event kind:" + event.kind() + ". File affected: " + event.context() + ".");
            }
            key.reset();
        }

        exit(0);
    }
}
