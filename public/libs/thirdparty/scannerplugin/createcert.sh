#keytool -validity 999999 -genkey -keyalg rsa -alias onebitCert

javac -classpath /usr/lib/jvm/java-6-sun-1.6.0.26/jre/lib/plugin.jar:/home/appelt/mount/projects/contreras/libs/thirdparty/scannerplugin uk/co/mmscomputing/device/sane/applet/coplan.java
javac -classpath /usr/lib/jvm/java-6-sun-1.6.0.26/jre/lib/plugin.jar:/home/appelt/mount/projects/contreras/libs/thirdparty/scannerplugin uk/co/mmscomputing/device/twain/applet/coplan.java

keytool -certreq -alias onebitCert
rm -rf scannerplugin.jar
rm -rf temp
mkdir temp
cp -R * temp/
cd temp
find ./uk -name "*.java"|xargs rm -f
jar cvf scannerplugin.jar ./uk ./org
jarsigner scannerplugin.jar onebitCert
mv scannerplugin.jar ../
cd ..
rm -rf temp
