#keytool -validity 999999 -genkey -keyalg rsa -alias onebitCert
javac de/applet/onebit.java
keytool -certreq -alias onebitCert
rm -rf scannerplugin.jar
rm -rf temp
mkdir temp
cp -R * temp/
cd temp
find ./de -name "*.java"|xargs rm -f
jar cvf 1bitjavacmd.jar ./de
jarsigner 1bitjavacmd.jar onebitCert
mv 1bitjavacmd.jar ../
cd ..
rm -rf temp

