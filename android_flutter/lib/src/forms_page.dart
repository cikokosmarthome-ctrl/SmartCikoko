import 'dart:math';
import 'package:flutter/material.dart';
import 'api_client.dart';
import 'design.dart';

String newFormNonce() {
  final rnd = Random.secure();
  return List.generate(16, (_) => rnd.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
}

/// Server returns only categories the logged-in identity may create.
class FormsPage extends StatefulWidget {
  final String token;
  const FormsPage({super.key, required this.token});
  @override State<FormsPage> createState() => _FormsPageState();
}
class _FormsPageState extends State<FormsPage> {
  late Future<List<Map<String,dynamic>>> request;
  @override void initState(){super.initState();request = getCatalog();}
  void retry() => setState(() => request = getCatalog());
  Future<List<Map<String,dynamic>>> getCatalog() async {
    final r=await PortalApi.forms('catalog',token:widget.token);
    return PortalApi.rows(r['data']);
  }
  @override Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title:const Text('Formulir Digital')),
    body:FutureBuilder<List<Map<String,dynamic>>>(
      future:request,builder:(ctx,snap){
        if(!snap.hasData) {
          if(snap.hasError)return Center(child:Column(
            mainAxisSize:MainAxisSize.min,children:[
              CivicEmpty('Formulir tidak tersedia: '+snap.error.toString()),
              TextButton.icon(onPressed:retry,icon:const Icon(Icons.refresh),
                label:const Text('Coba lagi')),
            ],
          ));
          return const Center(child:CircularProgressIndicator());
        }
        return ListView(padding:const EdgeInsets.all(18),children:[
          const CivicPageTitle(title:'Pilih Laporan',subtitle:'Formulir sesuai hak akses akun Anda.'),
          const SizedBox(height:18),
          if(snap.data!.isEmpty)const CivicEmpty('Belum ada formulir untuk peran akun ini.'),
          for(final schema in snap.data!) Padding(
            padding:const EdgeInsets.only(bottom:10),
            child:Card(child:ListTile(
              contentPadding:const EdgeInsets.all(12),
              leading:const CircleAvatar(backgroundColor:Color(0xFFE7F2FA),
                child:Icon(Icons.assignment_outlined,color:CivicColors.blue)),
              title:Text((schema['title']??'').toString(),
                style:const TextStyle(fontWeight:FontWeight.w800)),
              subtitle:Text((schema['description']??'').toString(),maxLines:2,overflow:TextOverflow.ellipsis),
              trailing:const Icon(Icons.chevron_right),
              onTap:()=>Navigator.push(context,MaterialPageRoute(
                builder:(_)=>FormRecordsPage(token:widget.token,schema:schema))),
            )),
          ),
        ]);
      },
    ),
  );
}

/// For mobile list rows, the server excludes payloads and other actors' IDs.
class FormRecordsPage extends StatefulWidget {
  final String token;
  final Map<String,dynamic> schema;
  const FormRecordsPage({super.key,required this.token,required this.schema});
  @override State<FormRecordsPage> createState()=>_FormRecordsPageState();
}
class _FormRecordsPageState extends State<FormRecordsPage> {
  late Future<Map<String,dynamic>> request;
  String get group=>(widget.schema['group']??'').toString();
  String get kind=>(widget.schema['kind']??'').toString();
  @override void initState(){super.initState();refresh();}
  void refresh() {
    request=PortalApi.forms('list',token:widget.token,
      query:{'group':group,'kind':kind})
      .then((r)=>Map<String,dynamic>.from(r['data'] as Map));
  }
  Future<void> openForm([Map<String,dynamic>? row])async{
    final changed=await Navigator.push<bool>(context,MaterialPageRoute(
      builder:(_)=>FormEditorPage(token:widget.token,schema:widget.schema,
        id:(row?['id'] as num?)?.toInt())));
    if(changed==true&&mounted)setState(refresh);
  }
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:Text((widget.schema['title']??'').toString())),
    body:FutureBuilder<Map<String,dynamic>>(future:request,builder:(ctx,snap){
      if(snap.hasError)return CivicEmpty('Tidak dapat memuat laporan: '+snap.error.toString());
      if(!snap.hasData)return const Center(child:CircularProgressIndicator());
      final rows=PortalApi.rows(snap.data!['rows']);
      return RefreshIndicator(
        onRefresh:()async{setState(refresh);await request;},
        child:ListView(padding:const EdgeInsets.all(18),children:[
          const CivicPageTitle(title:'Laporan Saya',
            subtitle:'Draf, pengajuan, dan status dari akun ini.'),
          const SizedBox(height:15),
          FilledButton.icon(onPressed:()=>openForm(),icon:const Icon(Icons.add),
            label:const Text('Buat Laporan Baru')),
          const SizedBox(height:20),
          if(rows.isEmpty)const CivicEmpty('Belum ada laporan. Mulai dengan tombol di atas.'),
          for(final row in rows) Padding(padding:const EdgeInsets.only(bottom:9),
            child:Card(child:ListTile(
              title:Text('Laporan #'+(row['id']??'').toString(),
                style:const TextStyle(fontWeight:FontWeight.w800)),
              subtitle:Text((row['updated_at']??'').toString(),
                style:const TextStyle(color:CivicColors.muted,fontSize:11)),
              trailing:CivicStatus(status:(row['status']??'').toString()),
              onTap:()=>openForm(row),
            ))),
        ]),
      );
    }),
  );
}

class FormEditorPage extends StatefulWidget {
  final String token;
  final Map<String,dynamic> schema;
  final int? id;
  const FormEditorPage({super.key,required this.token,required this.schema,this.id});
  @override State<FormEditorPage> createState()=>_FormEditorPageState();
}
class _FormEditorPageState extends State<FormEditorPage> {
  final key=GlobalKey<FormState>();
  final inputs=<String,TextEditingController>{};
  late final String nonce;
  bool loading=false,saving=false;
  String? error;
  int version=1;
  String status='draf';
  List<Map<String,dynamic>> get fields=>PortalApi.rows(widget.schema['fields']);
  String get group=>(widget.schema['group']??'').toString();
  String get kind=>(widget.schema['kind']??'').toString();
  bool get editable=>widget.id==null||status=='draf'||status=='perbaikan';
  @override void initState(){
    super.initState();nonce=newFormNonce();
    for(final f in fields)inputs[(f['key']??'').toString()]=TextEditingController();
    if(widget.id!=null)load();
  }
  Future<void> load()async{
    setState(()=>loading=true);
    try{
      final r=await PortalApi.forms('detail',token:widget.token,
        query:{'group':group,'id':widget.id.toString()});
      final data=Map<String,dynamic>.from(r['data'] as Map);
      status=(data['status']??'draf').toString();
      version=(data['version'] as num?)?.toInt()??1;
      final values=Map<String,dynamic>.from(data['values'] as Map? ?? {});
      for(final f in fields){
        final k=(f['key']??'').toString();
        inputs[k]?.text=(values[k]??'').toString();
      }
    }catch(e){error='Rincian formulir belum bisa dibuka: '+e.toString();}
    if(mounted)setState(()=>loading=false);
  }
  @override void dispose(){for(final c in inputs.values){c.dispose();}super.dispose();}
  Future<void> chooseDate(String field,String type)async{
    if(type=='time'){
      final t=await showTimePicker(context:context,initialTime:TimeOfDay.now());
      if(t!=null)inputs[field]?.text=t.hour.toString().padLeft(2,'0')+':'+t.minute.toString().padLeft(2,'0');
    }else{
      final d=await showDatePicker(context:context,initialDate:DateTime.now(),
        firstDate:DateTime(2020),lastDate:DateTime(2100));
      if(d!=null){
        final month=d.month.toString().padLeft(2,'0');
        inputs[field]?.text=type=='month'?d.year.toString()+'-'+month:
          d.year.toString()+'-'+month+'-'+d.day.toString().padLeft(2,'0');
      }
    }
    if(mounted)setState((){});
  }
  Widget input(Map<String,dynamic> spec){
    final field=(spec['key']??'').toString();
    final type=(spec['type']??'text').toString();
    final title=(spec['label']??field).toString()+(spec['required']==true?' *':'');
    final opts=spec['options'] is Map?Map<String,dynamic>.from(spec['options'] as Map):<String,dynamic>{};
    final ctrl=inputs[field]!;
    if(type=='select'){
      return DropdownButtonFormField<String>(
        initialValue:ctrl.text.isEmpty?null:ctrl.text,
        isExpanded:true,decoration:InputDecoration(labelText:title),
        items:opts.entries.map((e)=>DropdownMenuItem(value:e.key,child:Text(e.value.toString()))).toList(),
        onChanged:editable?(v)=>setState(()=>ctrl.text=v??''):null,
        validator:(v)=>spec['required']==true&&(v==null||v.isEmpty)?'Wajib diisi':null);
    }
    final dateType=['date','month','time'].contains(type);
    return TextFormField(
      controller:ctrl,readOnly:dateType||!editable,
      maxLines:type=='textarea'?3:1,
      keyboardType:['number','rt','count'].contains(type)?TextInputType.number:
        type=='tel'?TextInputType.phone:type=='textarea'?TextInputType.multiline:TextInputType.text,
      decoration:InputDecoration(labelText:title,alignLabelWithHint:type=='textarea',
        suffixIcon:dateType?const Icon(Icons.calendar_month_outlined):null),
      onTap:editable&&dateType?()=>chooseDate(field,type):null,
      validator:(value)=>spec['required']==true&&(value==null||value.trim().isEmpty)
        ?'Wajib diisi':null,
    );
  }
  Future<void> save(bool submit)async{
    if(saving||!editable||!key.currentState!.validate())return;
    setState((){saving=true;error=null;});
    final values=<String,String>{};
    for(final item in fields){
      final k=(item['key']??'').toString();
      values[k]=(inputs[k]?.text??'').trim();
    }
    try{
      await PortalApi.forms(widget.id==null?'create':'update',method:'POST',
        token:widget.token,body:{
          'group':group,'kind':kind,'values':values,'submit':submit,
          if(widget.id==null)'nonce':nonce,
          if(widget.id!=null)'id':widget.id,
          if(widget.id!=null)'version':version,
        });
      if(!mounted)return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content:Text(submit?'Laporan berhasil diajukan.':'Draf berhasil disimpan.')));
      Navigator.pop(context,true);
    }catch(e){if(mounted)setState(()=>error=e.toString());}
    if(mounted)setState(()=>saving=false);
  }
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:Text(widget.id==null?'Formulir Baru':'Laporan #'+widget.id.toString())),
    body:loading?const Center(child:CircularProgressIndicator()):
      Form(key:key,child:ListView(padding:const EdgeInsets.all(18),children:[
        CivicPageTitle(title:(widget.schema['title']??'').toString(),
          subtitle:widget.id==null?'Simpan draf atau ajukan laporan.':'Status: '+status),
        const SizedBox(height:13),
        Card(child:Padding(padding:const EdgeInsets.all(14),
          child:Text((widget.schema['description']??'').toString(),
            style:const TextStyle(color:CivicColors.muted,fontSize:12)))),
        const SizedBox(height:18),
        for(final item in fields)...[input(item),const SizedBox(height:12)],
        if(error!=null)Text(error!,style:const TextStyle(color:Colors.red)),
        if(editable)...[
          FilledButton.icon(onPressed:saving?null:()=>save(true),
            icon:const Icon(Icons.send),label:const Text('Ajukan Laporan')),
          const SizedBox(height:8),
          OutlinedButton.icon(onPressed:saving?null:()=>save(false),
            icon:const Icon(Icons.save_outlined),label:const Text('Simpan Draf')),
        ] else const CivicEmpty('Laporan sudah diajukan. Tunggu hasil pemeriksaan.'),
        if(saving)const LinearProgressIndicator(),
        const SizedBox(height:30),
      ])),
  );
}
