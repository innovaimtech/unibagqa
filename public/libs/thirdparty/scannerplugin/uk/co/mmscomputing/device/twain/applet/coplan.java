package uk.co.mmscomputing.device.twain.applet; 

//----------------------------------------------------------------------------------
import java.io.*;
import java.awt.Button;
import java.awt.GridLayout;
import java.applet.Applet;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.awt.event.WindowAdapter;
import java.awt.event.WindowEvent;
import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import uk.co.mmscomputing.device.ftp.FTPConnection;
import uk.co.mmscomputing.device.scanner.Scanner;
import uk.co.mmscomputing.device.scanner.ScannerDevice;
import uk.co.mmscomputing.device.scanner.ScannerListener;
import uk.co.mmscomputing.device.scanner.ScannerIOMetadata;
import uk.co.mmscomputing.device.scanner.ScannerIOException;
import javax.swing.JFrame;
import netscape.javascript.JSObject;
import java.util.UUID;

//----------------------------------------------------------------------------------
public class coplan extends Applet implements ActionListener, ScannerListener
{
   JSObject jso;

   String  filename;
   String docID;
   String conf_ftp_ip;
   String conf_ftp_user;
   String conf_ftp_pass;
   String conf_ftp_dir;

   Scanner scanner;
   Button  acquireButton,selectButton,cancelButton;

   public coplan(){ }

   //----------------------------------------------------------------------------------
   public coplan(String title, String[] argv)
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

      init();

      frame.getContentPane().add(this);
      frame.pack();
      frame.setLocationRelativeTo(null);
      frame.setVisible(true);

      start();
   }

   //----------------------------------------------------------------------------------
   public void init()
   {
      try { jso = JSObject.getWindow(this); } catch (Exception ex) { ;; }
      try { jso.call("setScanOutput", new String[] {"1BIT WebScanner initializado"}); } catch (Exception ex) { ;; }
      docID          = getParameter("docID");
      conf_ftp_ip    = getParameter("conf_ftp_ip");
      conf_ftp_user  = getParameter("conf_ftp_user");
      conf_ftp_pass  = getParameter("conf_ftp_pass");
      conf_ftp_dir   = getParameter("conf_ftp_dir");
    
      setLayout(new GridLayout(1,3));
      selectButton = new Button("Dispositivos");
      add(selectButton);
      selectButton.addActionListener(this);

      acquireButton = new Button("Escanear");
      add(acquireButton);
      acquireButton.addActionListener(this);

      filename=System.getProperty("user.home")+"\\scan.jpg";

      scanner=Scanner.getDevice();
      scanner.addListener(this);

   }

   //----------------------------------------------------------------------------------
   public void actionPerformed(ActionEvent evt)
   {
      try
      {
         if(evt.getSource()==acquireButton)
         {
            scanner.acquire();
         }else if(evt.getSource()==selectButton)
         {
            scanner.select();
         }else if(evt.getSource()==cancelButton)
         {
            scanner.setCancel(true);
         }
      }
      catch(ScannerIOException se)
      {
         se.printStackTrace();
      }
   }

   //----------------------------------------------------------------------------------
   public void update(ScannerIOMetadata.Type type, ScannerIOMetadata metadata)
   {
      if(type.equals(ScannerIOMetadata.ACQUIRED))
      {
         BufferedImage image=metadata.getImage();
         
         try
         {
            ImageIO.write(image, "jpg", new File(filename));
            try { jso.call("setScanStep", new String[] {"2"}); } catch (Exception ex) { ;; }

            //-----------------------------------------------------------------------------------------
            try
            {
               FTPConnection ftp = new FTPConnection();
               String serverName = conf_ftp_ip;
               String uuid = UUID.randomUUID().toString();
               uuid = uuid.replace("-", "");
               
               if (ftp.connectAndLogin(serverName, conf_ftp_user, conf_ftp_pass))
               {
                  try { jso.call("setScanOutput", new String[] {"Contectado con " +serverName}); } catch (Exception ex) { ;; }
                  try
                  {
                     ftp.setPassiveMode(true);
                     try { jso.call("setScanOutput", new String[] {"Transferiendo Imagen"}); } catch (Exception ex) { ;; }
                     ftp.binary();
                     ftp.uploadFile(filename, conf_ftp_dir +docID +"." +uuid +".jpg");
                  }
                  catch (Exception ftpe)
                  {
                     ftpe.printStackTrace();
                  }
                  finally
                  {
                     ftp.logout();
                     ftp.disconnect();
                     try { jso.call("setScanStep", new String[] {uuid}); } catch (Exception ex) { ;; }
                  }
               }
               else
               {
                  try { jso.call("setScanOutput", new String[] {"Problemas de Conexion " +serverName}); } catch (Exception ex) { ;; }
               }
            }
            catch(Exception e)
            {
               e.printStackTrace();
            }
         }
         catch(Exception e)
         {
            e.printStackTrace();
         }
      }
      else if(type.equals(ScannerIOMetadata.NEGOTIATE))
      {
         ScannerDevice device=metadata.getDevice();

         try
         {
            device.setResolution(150);
            device.setShowUserInterface(true);
            device.setShowProgressBar(true);
         }
         catch(Exception e)
         {
            e.printStackTrace();
         }
      }
      else if(type.equals(ScannerIOMetadata.STATECHANGE))
      {
         System.err.println(metadata.getStateStr());
      }
      else if(type.equals(ScannerIOMetadata.EXCEPTION))
      {
         metadata.getException().printStackTrace();
      }
   }

   //-----------------------------------------------------------------------------------------
   public static void main(String[] argv)
   {
      try
      {
         new coplan("1BIT WebScanner", argv);
      }
      catch(Exception e)
      {
         e.printStackTrace();
      }
   }
}