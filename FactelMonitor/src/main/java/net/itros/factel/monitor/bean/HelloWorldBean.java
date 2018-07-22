package net.itros.factel.monitor.bean;

import org.springframework.stereotype.Component;

@Component
public class HelloWorldBean {
    public void sayHello(){
        System.out.println("Hello Spring Boot!");
    }
}