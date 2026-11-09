import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'citizen_requests.dart';

const apiBase='https://datawebs.my.id/api/';
const secure=FlutterSecureStorage();
void main()=>runApp(const SmartCikoko());

class PortalApi {
 static Future<Map<String,dynamic>> call(String resource,
     {String method='GET',String? token,Map<String,dynamic>? body})async{
   final uri=Uri.parse(apiBase).replace(queryParameters:{'resource':resource});
   final headers=<String,String>{'Accept':'application/json',
      if(token!=null)'Authorization':'Bearer '+token,
      if(method=='POST')'Content-Type':'application/json'};
   final response=method=='POST'
      ?await http.post(uri,headers:headers,body:jsonEncode(body??{})).timeout(const Duration(seconds:20))
      :await http.get(uri,headers:headers).timeout(const Duration(seconds:20));
   final parsed=jsonDecode(response.body);
   if(parsed is! Map<String,dynamic>)throw Exception('Format respons server tidak valid.');
   if(resource=='login' && parsed['mfa_required']==true) return parsed;
   if(response.statusCode>=400||parsed['ok']!=true)throw Exception(
     (parsed['error']??'Permintaan tidak berhasil.').toString());
   return parsed;
 }
}
class SmartCikoko extends StatelessWidget{
 const SmartCikoko({super.key});
 @override Widget build(BuildContext context)=>MaterialApp(
  title:'SmartCikoko',debugShowCheckedModeBanner:false,
  theme:ThemeData(useMaterial3:true,
   colorScheme:ColorScheme.fromSeed(seedColor:const Color(0xFF19466A)),
   scaffoldBackgroundColor:const Color(0xFFF3F7FA)),
  home:const Portal());
}
class Portal extends StatefulWidget{
 const Portal({super.key});
 @override State<Portal> createState()=>_PortalState();
}
class _PortalState extends State<Portal>{
 String? token; Map<String,dynamic> user={},permissions={}; bool loading=true;
 @override void initState(){super.initState();restore();}
 Future<void> restore()async{
   final t=await secure.read(key:'portal_token');
   if(t!=null){
     try{
       final response=await PortalApi.call('me',token:t);
       if(mounted)setState((){
         token=t;user=Map<String,dynamic>.from(response['user']);
         permissions=Map<String,dynamic>.from(response['permissions']??{});
       });
     }catch(_){await secure.delete(key:'portal_token');}
   }
   if(mounted)setState(()=>loading=false);
 }
 Future<void> login(String t)async{
   await secure.write(key:'portal_token',value:t);
   if(mounted)setState(()=>loading=true);
   await restore();
 }
 Future<void> logout()async{
   try{await PortalApi.call('logout',method:'POST',token:token);}catch(_){}
   await secure.delete(key:'portal_token');
   if(mounted)setState((){token=null;user={};permissions={};});
 }
 List<Module> modules(){
   final modules=<Module>[];
   if((user['source']=='smartapp' && ['warga','ptsp','lurah'].contains(user['role'])) ||
       permissions['super_admin']==true) {
     modules.add(const Module('Pengajuan Pelayanan','citizen_requests',Icons.fact_check_outlined));
   }
   if(permissions['super_admin']==true)modules.add(const Module('Pusat Super Admin','superadmin_dashboard',Icons.admin_panel_settings_outlined));
   if(permissions['admin']==true)modules.add(const Module('Data Pegawai','pegawai',Icons.badge_outlined));
   if(permissions['pppk']==true)modules.add(const Module('Laporan PPPK','pppk',Icons.assignment_outlined));
   if(permissions['pjlp']==true)modules.add(const Module('Kegiatan PJLP','pjlp',Icons.groups_outlined));
   if(permissions['waris']==true)modules.add(const Module('Ahli Waris','waris',Icons.description_outlined));
   return modules;
 }
 @override Widget build(BuildContext context){
   if(loading)return const Scaffold(body:Center(child:CircularProgressIndicator()));
   if(token==null)return Login(onLogin:login);
   return Scaffold(
     appBar:AppBar(title:const Text('SMARTCIKOKO')),
     drawer:Drawer(child:SafeArea(child:ListView(children:[
       const ListTile(title:Text('KELURAHAN CIKOKO'),subtitle:Text('Portal Pelayanan Terpadu')),
       ListTile(leading:const Icon(Icons.person),title:Text((user['name']??'').toString()),
         subtitle:Text((user['role']??'').toString().toUpperCase())),
       for(final m in modules())ListTile(leading:Icon(m.icon),title:Text(m.title),onTap:(){
         Navigator.pop(context);
         Navigator.push(context,MaterialPageRoute(builder:(_)=>
           m.resource=='citizen_requests'
             ? CitizenRequestsScreen(token:token!, user:user)
             : m.resource=='superadmin_dashboard'
               ? SuperAdminPanel(token:token!)
               : Records(resource:m.resource,token:token!,title:m.title)));
       }),
       const Divider(),
       ListTile(leading:const Icon(Icons.logout),title:const Text('Keluar'),onTap:logout)
     ]))),
     body:ListView(padding:const EdgeInsets.all(18),children:[
       Card(color:const Color(0xFF153D61),child:Padding(padding:const EdgeInsets.all(24),
         child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
           const Icon(Icons.account_balance,color:Colors.white,size:36),
           const SizedBox(height:12),
           Text('Selamat datang, '+(user['name']??'').toString(),
             style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w800,fontSize:23)),
           Text((user['role']??'').toString().toUpperCase(),
             style:const TextStyle(color:Color(0xFFD2E3F2))),
         ]))),
       const SizedBox(height:15),
       FutureBuilder<Map<String,dynamic>>(future:PortalApi.call('dashboard',token:token),
        builder:(context,snapshot){
         if(snapshot.hasError)return Text(snapshot.error.toString());
         if(!snapshot.hasData)return const Center(child:CircularProgressIndicator());
         final data=Map<String,dynamic>.from(snapshot.data?['data']??{});
         return Column(children:(data['metrics'] as List? ?? []).map((m)=>Card(
          child:ListTile(title:Text((m['label']??'').toString()),trailing:Text(
            (m['value']??0).toString(),style:const TextStyle(fontWeight:FontWeight.bold,fontSize:22))))).toList());
        }),
       const SizedBox(height:15),
       const Text('Modul berdasarkan kewenangan',style:TextStyle(fontWeight:FontWeight.bold,fontSize:20)),
       for(final m in modules())Card(child:ListTile(leading:Icon(m.icon),title:Text(m.title),
         trailing:const Icon(Icons.arrow_forward_ios,size:15),
         onTap:()=>Navigator.push(context,MaterialPageRoute(
          builder:(_)=>m.resource=='citizen_requests'
              ? CitizenRequestsScreen(token:token!, user:user)
              : m.resource=='superadmin_dashboard'
                ? SuperAdminPanel(token:token!)
                : Records(resource:m.resource,token:token!,title:m.title))))),
       const SizedBox(height:20),const PublicNews()
     ])
   );
 }
}
class Module{
 final String title,resource;final IconData icon;
 const Module(this.title,this.resource,this.icon);
}
class Login extends StatefulWidget{
 final ValueChanged<String> onLogin;
 const Login({super.key,required this.onLogin});
 @override State<Login> createState()=>_LoginState();
}
class _LoginState extends State<Login>{
 final username=TextEditingController(),password=TextEditingController(),otp=TextEditingController();
 bool busy=false,obscure=true,mfaRequired=false;String? error;List<dynamic> choices=[];
 Map<String,dynamic>? selectedAccount;
 @override void dispose(){username.dispose();password.dispose();otp.dispose();super.dispose();}
 Future<void> submit([Map<String,dynamic>? selected])async{
   if(busy)return;
   setState((){busy=true;error=null;});
   try{
     if(selected!=null) selectedAccount=selected;
     final selectedUser=selected ?? selectedAccount;
     final r=await PortalApi.call('login',method:'POST',body:{
       'username':username.text.trim(),'password':password.text,
       if(selectedUser!=null)'source':selectedUser['source'],
       if(selectedUser!=null)'id':selectedUser['id'],
       if(mfaRequired)'otp':otp.text.trim()});
     if(r['selection_required']==true){
       if(mounted)setState(()=>choices=r['accounts'] as List? ?? []);
     }else if(r['mfa_required']==true){
       if(mounted)setState(()=>mfaRequired=true);
     }else{
       password.clear();otp.clear();widget.onLogin(r['token'].toString());
     }
   }catch(e){if(mounted)setState(()=>error=e.toString());}
   if(mounted)setState(()=>busy=false);
 }
 @override Widget build(BuildContext context)=>Scaffold(
   appBar:AppBar(title:const Text('KELURAHAN CIKOKO')),
   body:ListView(padding:const EdgeInsets.all(20),children:[
     Card(color:const Color(0xFF123958),
       child:Padding(padding:const EdgeInsets.all(22),child:Column(
         crossAxisAlignment:CrossAxisAlignment.start,children:[
           const Icon(Icons.account_balance,size:35,color:Colors.amber),
           const SizedBox(height:15),
           const Text('Portal Pelayanan dan Administrasi Terpadu',
             style:TextStyle(color:Colors.white,fontSize:24,fontWeight:FontWeight.w800)),
           const SizedBox(height:8),
           const Text('Melayani secara profesional, transparan, dan akuntabel.',
             style:TextStyle(color:Color(0xFFD7E6F3)))
         ]))),
     const SizedBox(height:15),
     Card(child:Padding(padding:const EdgeInsets.all(20),child:Column(
       crossAxisAlignment:CrossAxisAlignment.start,children:[
       const Text('Masuk ke Ruang Kerja',style:TextStyle(fontSize:21,fontWeight:FontWeight.w800)),
       const SizedBox(height:15),
       if(mfaRequired)...[
         const Text('Kode keamanan tambahan diperlukan untuk akun Super Admin.',
           style:TextStyle(fontSize:13)),
         const SizedBox(height:15),
         TextField(controller:otp,keyboardType:TextInputType.text,maxLength:19,
           decoration:const InputDecoration(labelText:'Kode autentikator / cadangan',
             border:OutlineInputBorder())),
         const SizedBox(height:12),
         FilledButton(onPressed:busy?null:()=>submit(),
           child:const Text('Verifikasi Dua Langkah')),
         TextButton(onPressed:busy?null:()=>setState((){
           mfaRequired=false;otp.clear();selectedAccount=null;choices=[];
         }),child:const Text('Kembali ke Login')),
       ]else if(choices.isEmpty)...[
         TextField(controller:username,decoration:const InputDecoration(
           labelText:'Username atau Email',border:OutlineInputBorder())),
         const SizedBox(height:12),
         TextField(controller:password,obscureText:obscure,decoration:InputDecoration(
           labelText:'Kata Sandi',border:const OutlineInputBorder(),
           suffixIcon:IconButton(onPressed:()=>setState(()=>obscure=!obscure),
             icon:Icon(obscure?Icons.visibility:Icons.visibility_off)))),
         const SizedBox(height:15),
         FilledButton(onPressed:busy?null:()=>submit(),child:const Text('Masuk Portal'))
       ]else...[
         const Text('Pilih ruang kerja yang akan digunakan.'),
         for(final c in choices)ListTile(title:Text((c['name']??'').toString()),
          subtitle:Text((c['role_label']??'').toString()),
          onTap:busy?null:()=>submit(Map<String,dynamic>.from(c))),
         TextButton(onPressed:()=>setState(()=>choices=[]),child:const Text('Kembali'))
       ],
       if(busy)const LinearProgressIndicator(),
       if(error!=null)Text(error!,style:const TextStyle(color:Colors.red)),
     ]))),
     const SizedBox(height:20),const PublicNews(),
   ]));
}

class SuperAdminPanel extends StatelessWidget {
  final String token;
  const SuperAdminPanel({super.key,required this.token});
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('Pusat Super Admin')),
    body:FutureBuilder<Map<String,dynamic>>(
      future:PortalApi.call('superadmin_dashboard',token:token),
      builder:(context,snap) {
        if(snap.hasError) {
          return Center(child:Padding(padding:const EdgeInsets.all(20),
            child:Text('Pusat kendali belum tersedia: '+snap.error.toString())));
        }
        if(!snap.hasData)return const Center(child:CircularProgressIndicator());
        final data=Map<String,dynamic>.from(snap.data?['data']??{});
        final health=Map<String,dynamic>.from(data['health']??{});
        final connections=Map<String,dynamic>.from(health['connections']??{});
        final metrics=health['metrics'] as List? ?? [];
        final modules=data['modules'] as List? ?? [];
        return ListView(padding:const EdgeInsets.all(18),children:[
          Card(color:const Color(0xFF143F65),child:Padding(
            padding:const EdgeInsets.all(22),child:Column(
              crossAxisAlignment:CrossAxisAlignment.start,children:[
                const Icon(Icons.admin_panel_settings,color:Colors.white,size:36),
                const SizedBox(height:12),
                const Text('Pusat Kendali Terpadu',
                  style:TextStyle(color:Colors.white,fontSize:24,fontWeight:FontWeight.w800)),
                const SizedBox(height:6),
                const Text('Pengawasan dua database dan progres setiap modul.',
                  style:TextStyle(color:Color(0xFFD7E7F1))),
              ]))),
          const SizedBox(height:15),
          const Text('Status Integrasi',style:TextStyle(fontSize:19,fontWeight:FontWeight.w800)),
          for(final e in connections.entries) Card(child:ListTile(
            leading:Icon(e.value==true?Icons.check_circle:Icons.error_outline,
              color:e.value==true?Colors.green:Colors.red),
            title:Text(e.key=='pegawai'?'Database Pegawai':'Database SmartApp'),
            subtitle:Text(e.value==true?'Terhubung':'Gangguan koneksi'))),
          const SizedBox(height:15),
          const Text('Rekap Operasional',style:TextStyle(fontSize:19,fontWeight:FontWeight.w800)),
          for(final m in metrics) Card(child:ListTile(
            title:Text((m['title']??'').toString()),
            trailing:Text((m['value']??'—').toString(),
              style:const TextStyle(fontSize:21,fontWeight:FontWeight.bold)))),
          const SizedBox(height:15),
          const Text('Progres Pekerjaan',style:TextStyle(fontSize:19,fontWeight:FontWeight.w800)),
          const Padding(padding:EdgeInsets.symmetric(vertical:8),
            child:Text('Progres dilaporkan oleh Super Admin melalui website.')),
          if(data['progress_configured']!=true)
            const Card(child:ListTile(title:Text('Migrasi tabel progres belum lengkap'),
              subtitle:Text('Periksa instalasi schema Super Admin di phpMyAdmin.'))),
          for(final m in modules)
            Card(child:Padding(padding:const EdgeInsets.all(16),
              child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
                Text((m['label']??'').toString(),
                  style:const TextStyle(fontWeight:FontWeight.w800,fontSize:15)),
                const SizedBox(height:5),
                Text((m['status']??'belum').toString().toUpperCase()+
                  ' • '+(m['progress_percent']??0).toString()+'%'),
                const SizedBox(height:10),
                LinearProgressIndicator(value:((m['progress_percent']??0) as num)
                  .toDouble().clamp(0,100)/100),
                if((m['notes']??'').toString().isNotEmpty)
                  Padding(padding:const EdgeInsets.only(top:10),
                    child:Text(m['notes'].toString())),
              ]))),
          const SizedBox(height:20),
        ]);
      },
    ),
  );
}

class Records extends StatelessWidget{
 final String resource,token,title;
 const Records({super.key,required this.resource,required this.token,required this.title});
 @override Widget build(BuildContext context)=>Scaffold(
  appBar:AppBar(title:Text(title)),
  body:FutureBuilder<Map<String,dynamic>>(future:PortalApi.call(resource,token:token),
   builder:(context,snap){
     if(snap.hasError)return Center(child:Text(snap.error.toString()));
     if(!snap.hasData)return const Center(child:CircularProgressIndicator());
     final records=snap.data?['data'] as List? ?? [];
     if(records.isEmpty)return const Center(child:Text('Belum terdapat data.'));
     return ListView.builder(padding:const EdgeInsets.all(12),itemCount:records.length,
      itemBuilder:(context,i){
       final record=records[i];
       if(record is! Map)return const SizedBox.shrink();
       return Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(
         crossAxisAlignment:CrossAxisAlignment.start,
         children:record.entries.map((e)=>Padding(
           padding:const EdgeInsets.only(bottom:6),
           child:Text(e.key.toString().replaceAll('_',' ')+': '+(e.value??'—').toString()))).toList())));
      });
   }));
}
class PublicNews extends StatelessWidget{
 const PublicNews({super.key});
 @override Widget build(BuildContext context)=>Column(
  crossAxisAlignment:CrossAxisAlignment.start,children:[
   const Text('Informasi Publik',style:TextStyle(fontSize:20,fontWeight:FontWeight.bold)),
   FutureBuilder<Map<String,dynamic>>(future:PortalApi.call('posts'),
    builder:(context,snap){
      if(!snap.hasData)return const Text('Berita belum tersedia.');
      final posts=snap.data?['data'] as List? ?? [];
      return Column(children:posts.take(5).map((post)=>Card(child:ListTile(
        title:Text((post['title']??'').toString()),
        subtitle:Text((post['summary']??'').toString(),maxLines:3)))).toList());
    })
 ]);
}
