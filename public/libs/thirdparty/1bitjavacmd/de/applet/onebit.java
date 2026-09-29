package de.applet;

//----------------------------------------------------------------------------------
import java.io.*;
import java.awt.Button;
import java.awt.GridLayout;
import java.applet.Applet;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.awt.event.WindowAdapter;
import java.awt.event.WindowEvent;
import javax.swing.JFrame;
import netscape.javascript.JSObject;
import java.util.UUID;
import java.security.*;

//----------------------------------------------------------------------------------
public class onebit extends Applet implements ActionListener
{
   JSObject jso;

   public onebit(){ }

   //----------------------------------------------------------------------------------
   public onebit(String title, String[] argv)
   {
      JFrame.setDefaultLookAndFeelDecorated(true);

      JFrame frame=new JFrame(title);

      frame.addWindowListener(new WindowAdapter()
      {
         public void windowClosing(WindowEvent ev)
         {
            stop();System.exit(0);
         }
      });

      frame.getContentPane().add(this);
      frame.pack();
      frame.setLocationRelativeTo(null);
      frame.setVisible(true);
      init();
   }

   //----------------------------------------------------------------------------------
   public void actionPerformed(ActionEvent evt)
   {
   }

   //----------------------------------------------------------------------------------
   public void launchScript(String cmd, String args)
   {
       try
       {
           if (cmd != null && !cmd.trim().equals(""))
           {
               if (args == null || args.trim().equals(""))
               {
                   final String tempcmd = cmd;
                   AccessController.doPrivileged(new PrivilegedAction() {
                   public Object run() {
                   try
                   {
                       Runtime.getRuntime().exec(tempcmd);
                   }
                   catch (Exception e)
                   {
                       System.out.println("Caught exception in privileged block, Exception:" + e.toString());
                   }
                   return null; // nothing to return
               }
               });
                   System.out.println(cmd);
               }
               else
               {
                   final String tempargs = args;
                   final String tempcmd1 = cmd;
                   AccessController.doPrivileged(new PrivilegedAction() {
                       public Object run()
                       {
                           try
                           {
                               Runtime.getRuntime().exec(tempcmd1 + " " + tempargs);
                           }
                           catch (Exception e)
                           {
                               System.out.println("Caught exception in privileged block, Exception:" + e.toString());
                           }
                           return null; // nothing to return
                       }
                   });
                   System.out.println(cmd + " " + args);
               }
           }
           else
           {
               System.out.println("execCmd parameter is null or empty");
           }
       }
       catch (Exception e)
       {
           System.out.println("Error executing command --> " + cmd + " (" + args + ")");
           System.out.println(e);
       }
   }

   public void init()
   {
     try {
         jso = JSObject.getWindow(this);
         jso.call("appletloaded", new String[] {"1"});
     }
     catch(Exception e) {
      ;;
     }
   }


   //-----------------------------------------------------------------------------------------
   public static void main(String[] argv)
   {
      try
      {
         new onebit("1bitjavacmd", argv);
      }
      catch(Exception e)
      {
         e.printStackTrace();
      }
   }
}